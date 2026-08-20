<?php

require_once __DIR__ . '/../classes/PlmState.php';

$_GET['plm'] =
    'eyJmaWx0ZXIiOnsiZm9ybTEiOnsiaXBuIjoieHl6IiwiZGVzY3JpcHRpb24iOiJzZXJ2ZXIifSwiZm9ybTIiOnsiaXBuIjoiYWJjIn19fQ';

$state =
    new PlmState();

echo "form1.ipn:\n";
var_dump(
    $state->getFilterValue(
        'form1',
        'ipn'
    )
);

echo "\nform1.description:\n";
var_dump(
    $state->getFilterValue(
        'form1',
        'description'
    )
);

echo "\nform2.ipn:\n";
var_dump(
    $state->getFilterValue(
        'form2',
        'ipn'
    )
);

echo "\nComplete state:\n";
var_dump(
    $state->get()
);