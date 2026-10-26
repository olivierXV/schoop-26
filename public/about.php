<?php
require __DIR__ . '/../app/bootstrap.php';
page_start('About', ['public' => true, 'active' => 'about']);
?>
<main id="main" class="narrow">
  <div class="card about">
    <img src="<?= e(url('assets/img/school.png')) ?>" alt="School seal" width="96" height="96">
    <h1>Senior High School, San Nicolas III, Bacoor City</h1>
    <ul class="plain">
      <li>Garnet St. Green Valley, San Nicolas III, City of Bacoor, Cavite</li>
      <li>Telephone: (046) 894 2825</li>
      <li>Email: 342600@deped.gov.ph</li>
    </ul>
    <h2>About SCHOOP</h2>
    <p>SCHOOP: Development of a School E-Portal for SHS in San Nicolas III, Bacoor City.</p>
    <h3>Researchers</h3>
    <ul class="plain"><li>Hernandez, Adrian Oliver V.</li><li>Quimay, Venedict Floyd S.</li><li>Florenciano, Kleinwise M.</li><li>Burgos, Mark Zyrus A.</li></ul>
    <h3>Research Adviser</h3>
    <p>Mr. Roderick F. Ferros</p>
  </div>
</main>
<?php page_end();
