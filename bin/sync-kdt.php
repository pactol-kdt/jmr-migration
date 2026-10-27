<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/bootstrap.php';

use Jmr\Domain\DirectorySync;
use Jmr\Domain\XmlImporter;
use Jmr\Support\Db;

$kdt = Db::kdt();
if ($kdt === null) {
    fwrite(STDERR, "Set DB_KDT_DATABASE in .env before running this command.\n");
    exit(1);
}

$rows = $kdt->query('SELECT fldEmployeeNum, fldName, fldUser, fldGroup, fldDesig, fldResignDate FROM emp_prof')->fetchAll();
$profiles = array_map(fn (array $row) => [
    'employee_id' => $row['fldEmployeeNum'],
    'name' => $row['fldName'],
    'username' => $row['fldUser'],
    'group_name' => $row['fldGroup'],
    'designation' => $row['fldDesig'],
    'resigned_on' => $row['fldResignDate'],
], $rows);

$result = (new DirectorySync(new XmlImporter()))->syncKdt($profiles);
echo "Updated {$result['updated']} employee profile(s).\n";
echo "Resigned: {$result['resigned']}.\n";
echo "Left unchanged because they are missing from the directory: {$result['unchanged']}.\n";
if ($result['skipped'] !== []) {
    echo 'Skipped '.count($result['skipped']).' employee(s) with a duplicate username: '.implode(', ', $result['skipped'])."\n";
}
