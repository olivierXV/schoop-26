<?php
declare(strict_types=1);

function school_years(): array { return array_column(db_all('SELECT SchoolYear FROM SchoolYear ORDER BY SchoolYear DESC'), 'SchoolYear'); }
function quarters_for(int $semester): array { return $semester === 1 ? ['Q1', 'Q2'] : ['Q3', 'Q4']; }
function current_semester(): int { return (int)config('app')['current_semester'] === 1 ? 1 : 2; }

/** Sections (with grade level) a staff member is assigned to teach. Admins see only their own teaching load, same as before. */
function staff_sections(string $teacherId): array {
    return db_all('SELECT DISTINCT sec.SectionID, sec.SectionName, ts.Grade FROM teacher_subjects ts JOIN sections sec ON sec.SectionID=ts.SectionID
                   WHERE ts.TeacherID=? ORDER BY ts.Grade, sec.SectionName', 's', [$teacherId]);
}
function staff_assignments(string $teacherId): array {
    return db_all('SELECT s.SubjectName, sec.SectionName, ts.Grade FROM teacher_subjects ts JOIN subject s ON s.SubjectID=ts.SubjectID
                   JOIN sections sec ON sec.SectionID=ts.SectionID WHERE ts.TeacherID=? ORDER BY ts.Grade, sec.SectionName, s.SubjectName', 's', [$teacherId]);
}

/** One student's own grades for a semester. LRN always comes from the session, never from the request. */
function student_semester_grades(string $lrn, string $grade, int $semester, string $schoolYear): array {
    [$qa, $qb] = quarters_for($semester);
    $rows = db_all("SELECT s.SubjectID, s.SubjectName, s.Type,
                           MAX(CASE WHEN g.quarter=? THEN g.quarter_grade END) AS qa,
                           MAX(CASE WHEN g.quarter=? THEN g.quarter_grade END) AS qb
                    FROM information i
                    JOIN sections sec ON sec.SectionID = i.SectionID
                    JOIN subject s ON (s.Strand = sec.StrandName OR s.Strand = 'All')
                                  AND (s.Track = sec.Track OR s.Track IS NULL)
                                  AND (s.Grade = ? OR s.Grade IS NULL)
                                  AND (s.Semester = ? OR s.Semester IS NULL)
                    LEFT JOIN grades g ON g.SubjectID = s.SubjectID AND g.LRN = i.LRN AND g.Grade = ? AND g.SchoolYear = ? AND g.quarter IN (?, ?)
                    WHERE i.LRN = ?
                    GROUP BY s.SubjectID, s.SubjectName, s.Type
                    ORDER BY FIELD(s.Type,'Core','Applied','Immersion','Specialized'), s.SubjectName",
        'sssssssss', [$qa, $qb, $grade, (string)$semester, $grade, $schoolYear, $qa, $qb, $lrn]);
    foreach ($rows as &$r) {
        $r['qa'] = $r['qa'] === null ? null : (int)$r['qa'];
        $r['qb'] = $r['qb'] === null ? null : (int)$r['qb'];
        $r['final'] = ($r['qa'] !== null && $r['qb'] !== null) ? (int)floor(($r['qa'] + $r['qb']) / 2 + 0.5) : null;
    }
    return $rows;
}

/** Class record for one section: only subjects this staff member is assigned to, only students in that section. */
function staff_class_rows(string $teacherId, int $sectionId, string $quarter, string $schoolYear): array {
    return db_all('SELECT i.LRN, i.LastName, i.FirstName, i.MiddleName, s.SubjectID, s.SubjectName, ts.Grade AS GradeLevel,
                          g.ww1,g.ww2,g.ww3,g.ww4,g.qz1,g.qz2,g.qz3,g.qz4,g.ww_qz_average,g.pt1,g.pt2,g.pt3,g.pt4,g.pt_average,g.qa,g.quarter_grade
                   FROM teacher_subjects ts
                   JOIN subject s ON s.SubjectID = ts.SubjectID
                   JOIN information i ON i.SectionID = ts.SectionID AND i.GradeLevel = ts.Grade
                   LEFT JOIN grades g ON g.SubjectID = s.SubjectID AND g.LRN = i.LRN AND g.Grade = ts.Grade AND g.quarter = ? AND g.SchoolYear = ?
                   WHERE ts.TeacherID = ? AND ts.SectionID = ? AND (s.Semester IS NULL OR s.Semester = ?)
                   ORDER BY s.SubjectName, i.LastName, i.FirstName', 'sssis', [$quarter, $schoolYear, $teacherId, $sectionId, in_array($quarter, ['Q1', 'Q2'], true) ? '1' : '2']);
}

/** May this staff member enter grades for this student in this subject? (assigned subject + student currently in that section/grade) */
function staff_may_grade(string $teacherId, int $subjectId, string $grade, string $lrn): bool {
    return (bool)db_one('SELECT 1 FROM teacher_subjects ts JOIN information i ON i.SectionID=ts.SectionID AND i.GradeLevel=ts.Grade
                         WHERE ts.TeacherID=? AND ts.SubjectID=? AND ts.Grade=? AND i.LRN=? LIMIT 1', 'siss', [$teacherId, $subjectId, $grade, $lrn]);
}

/** Subjects in the current semester where the student has no score recorded at all. */
function missing_subjects(array $u): array {
    $sem = current_semester(); [$qa, $qb] = quarters_for($sem);
    $grade = (string)$u['GradeLevel'];
    $has = array_column(db_all('SELECT DISTINCT SubjectID FROM grades WHERE LRN=? AND Grade=? AND SchoolYear=? AND quarter IN (?,?)
        AND COALESCE(ww1,ww2,ww3,ww4,qz1,qz2,qz3,qz4,pt1,pt2,pt3,pt4,qa,quarter_grade) IS NOT NULL', 'sssss', [$u['id'], $grade, $u['SchoolYear'], $qa, $qb]), 'SubjectID');
    $missing = [];
    foreach (student_semester_grades($u['id'], $grade, $sem, $u['SchoolYear']) as $r) {
        if (!in_array($r['SubjectID'], $has)) $missing[] = $r['SubjectName'];
    }
    return $missing;
}

function grade_chip(?int $g): string {
    [$cls, $label] = grade_band($g);
    return '<span class="grade g-' . $cls . '" title="' . e($label) . '">' . ($g === null ? '—' : (string)$g) . '</span>';
}
function num_cell($v): string { return $v === null ? '<span class="na">—</span>' : e(rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.')); }

/** Class record table. $edit=true renders score inputs for the editing page. */
function render_class_table(array $rows, bool $edit): void {
    $cols = ['ww1','ww2','ww3','ww4','qz1','qz2','qz3','qz4'];
    $pts  = ['pt1','pt2','pt3','pt4'];
    echo '<div class="table-wrap"><table class="records' . ($edit ? ' editing' : '') . '"><thead><tr><th class="sticky">Student</th>';
    foreach (['WW1','WW2','WW3','WW4','QZ1','QZ2','QZ3','QZ4'] as $h) echo "<th>$h</th>";
    echo '<th>WW+QZ avg</th>';
    foreach (['PT1','PT2','PT3','PT4'] as $h) echo "<th>$h</th>";
    echo '<th>PT avg</th><th>QA</th><th>Quarter grade</th></tr></thead><tbody>';
    $last = null;
    foreach ($rows as $r) {
        if ($r['SubjectID'] !== $last) {
            $last = $r['SubjectID'];
            echo '<tr class="group"><th colspan="19" scope="colgroup">' . e($r['SubjectName']) . '</th></tr>';
        }
        $name = $r['LastName'] . ', ' . $r['FirstName'] . ($r['MiddleName'] ? ' ' . mb_substr($r['MiddleName'], 0, 1) . '.' : '');
        echo '<tr data-lrn="' . e($r['LRN']) . '" data-subject="' . (int)$r['SubjectID'] . '" data-grade="' . e((string)$r['GradeLevel']) . '">';
        echo '<th class="sticky" scope="row">' . e($name) . '</th>';
        $cell = function (string $f) use ($r, $edit, $name) {
            if (!$edit) return '<td>' . num_cell($r[$f]) . '</td>';
            return '<td><input class="score" type="number" inputmode="numeric" min="0" max="100" step="1" data-field="' . $f . '" data-orig="' . e((string)($r[$f] ?? '')) . '"'
                 . ' value="' . e((string)($r[$f] ?? '')) . '" aria-label="' . e($name . ' ' . strtoupper($f)) . '"></td>';
        };
        foreach ($cols as $f) echo $cell($f);
        echo '<td class="calc" data-out="ww_qz_average">' . num_cell($r['ww_qz_average']) . '</td>';
        foreach ($pts as $f) echo $cell($f);
        echo '<td class="calc" data-out="pt_average">' . num_cell($r['pt_average']) . '</td>';
        echo $cell('qa');
        echo '<td class="calc" data-out="quarter_grade">' . grade_chip($r['quarter_grade'] === null ? null : (int)$r['quarter_grade']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/** Validates ?sy / ?section / ?quarter for staff pages. Returns sanitised filter values. */
function staff_filters(array $u): array {
    $years = school_years();
    $sy = in_array($_GET['sy'] ?? '', $years, true) ? $_GET['sy'] : ($years[0] ?? '');
    $sections = staff_sections($u['id']);
    $ids = array_column($sections, 'SectionID');
    $sec = (int)($_GET['section'] ?? 0);
    if (!in_array($sec, array_map('intval', $ids), true)) $sec = (int)($ids[0] ?? 0);
    $q = in_array($_GET['quarter'] ?? '', ['Q1','Q2','Q3','Q4'], true) ? $_GET['quarter'] : quarters_for(current_semester())[0];
    return compact('years', 'sy', 'sections', 'sec', 'q');
}
function staff_filter_form(array $f, string $action): void {
    echo '<form class="filters" method="get" action="' . e($action) . '">';
    echo '<label>School year<select name="sy">';
    foreach ($f['years'] as $y) echo '<option' . ($y === $f['sy'] ? ' selected' : '') . '>' . e($y) . '</option>';
    echo '</select></label><label>Section<select name="section">';
    foreach ($f['sections'] as $s) echo '<option value="' . (int)$s['SectionID'] . '"' . ((int)$s['SectionID'] === $f['sec'] ? ' selected' : '') . '>' . e($s['SectionName'] . ' (Grade ' . $s['Grade'] . ')') . '</option>';
    echo '</select></label><label>Quarter<select name="quarter">';
    foreach (['Q1','Q2','Q3','Q4'] as $q) echo '<option value="' . $q . '"' . ($q === $f['q'] ? ' selected' : '') . '>' . $q . '</option>';
    echo '</select></label><button class="btn" type="submit">Show</button></form>';
}
