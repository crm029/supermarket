<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/reports.php';

$cases = [
    '=1+1' => "'=1+1",
    '+SUM(A1:A2)' => "'+SUM(A1:A2)",
    '@cmd' => "'@cmd",
    "-formula" => "'-formula",
    "\n=1+1" => "'\n=1+1",
    "متن عادی" => "متن عادی",
];
foreach ($cases as $input=>$expected) {
    if (app_report_csv_value($input) !== $expected) {
        fwrite(STDERR,"CSV formula-injection guard failed for input: ".$input.PHP_EOL);
        exit(1);
    }
}
if (app_report_csv_value(-125) !== -125) {
    fwrite(STDERR,"Numeric values must remain numeric.".PHP_EOL);
    exit(1);
}
echo "CSV safety checks passed.".PHP_EOL;
