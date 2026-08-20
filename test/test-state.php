<?php

require_once __DIR__ . '/../classes/PlmState.php';

echo "=== Create state ===\n";

$state = new PlmState();

$state->setFilter(
    'form1',
    'ipn',
    'xyz'
);

$state->setFilter(
    'form1',
    'description',
    'server'
);

$state->setFilter(
    'form2',
    'ipn',
    'abc'
);

echo "\nValue form1.ipn:\n";

var_dump(
    $state->get('form1.ipn')
);

echo "\nValue form2.ipn:\n";

var_dump(
    $state->get('form2.ipn')
);

echo "\nFilter form1:\n";

var_dump(
    $state->getFilter('form1')
);

echo "\nComplete state:\n";

var_dump(
    $state->getState()
);

echo "\n=== Encode ===\n";

$encoded =
    $state->encode();

var_dump(
    $encoded
);

echo "\n=== Decode again ===\n";

$state2 =
    new PlmState($encoded);

echo "\nform1.ipn:\n";

var_dump(
    $state2->get('form1.ipn')
);

echo "\nform1.description:\n";

var_dump(
    $state2->get('form1.description')
);

echo "\nform2.ipn:\n";

var_dump(
    $state2->get('form2.ipn')
);

echo "\nComplete decoded state:\n";

var_dump(
    $state2->getState()
);