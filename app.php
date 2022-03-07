<?php

const EXIT_CHOICE = 5;

$mainMenu = [
    1 => 'Help',
    2 => 'Add',
    3 => 'Delete',
    4 => 'Path',
    5 => 'Exit',
];

$models = [
    1 => 'City',
    2 => 'Road'
];

$db = [
    'cities' => [],
    'roads' => []
];

function chooseModel()
{
    global $models;
    echo "Select model:" . PHP_EOL;
    foreach ($models as $key => $value) {
        echo $key . '. ' . $value . PHP_EOL;
    }

    $model = (int) trim(readline());
    handleChoice($model, $models);

    return $model;
}

function createCity()
{
    $schema = [
        'id' => 0,
        'name' => '',
    ];
    foreach ($schema as $key => $value) {
        echo "{$key}=?" . PHP_EOL;
        $schema[$key] = trim(readline());
    }

    echo <<<EOT
        City with id={$schema['id']} added!
        Select your next action
        1. Add another City
        2. Main Menu
        EOT . PHP_EOL;

    $choice = (int) trim(readline());
    handleChoice($choice, [1 => 'create', 2 => 'mainMenu']);

    global $db;
    $db['cities'][$schema['id']] = $schema;

    if ($choice == 2) {
        return;
    }

    createCity();
}

function createRoad()
{
    $schema = [
        'id' => 0,
        'name' => '',
        'from' => 0,
        'to' => 0,
        'through' => [],
        'speed_limit' => 0,
        'length' => 0,
        'bi_directional' => false,
    ];

    foreach ($schema as $key => $value) {
        if ($key == 'through') {
            echo "through=?" . PHP_EOL;
            $schema['through'] = explode(',', str_replace(['[', ']', ' '], '', trim(readline())));
        } else {
            echo "{$key}=?" . PHP_EOL;
            $schema[$key] = trim(readline());
        }
    }

    echo <<<EOT
    Road with id={$schema['id']} added!
    Select your next action
    1. Add another Road
    2. Main Menu
    EOT . PHP_EOL;

    $choice = (int) trim(readline());
    handleChoice($choice, [1 => 'create', 2 => 'mainMenu']);

    global $db;
    $db['roads'][$schema['id']][] = $schema;

    if ($choice == 2) {
        return;
    }
    
    createRoad();
}

function HelpAction()
{
    echo "Select a number from shown menu and enter. For example 1 is for help." . PHP_EOL;
}

function AddAction()
{
    $model = chooseModel();

    switch ($model) {
        case 1:
            createCity();
            break;
        case 2:
            createRoad();
            break;
        default:
            AddAction();
            break;
    }
}

function deleteCity()
{
    global $db;
    $id = (int) trim(readline());
    if (!isset($db['cities'][$id])) {
        echo "City with id={$id} not found!" . PHP_EOL;
        return;
    }
    unset($db['cities'][$id]);
    echo "City:{$id} deleted!" . PHP_EOL;
}

function deleteRoad()
{
    global $db;
    $id = (int) trim(readline());
    if (!isset($db['roads'][$id])) {
        echo "Road with id={$id} not found!" . PHP_EOL;
        return;
    }
    unset($db['roads'][$id]);
    echo "Road:{$id} deleted!" . PHP_EOL;
}

function DeleteAction()
{
    $model = chooseModel();

    switch ($model) {
        case 1:
            deleteCity();
            break;
        case 2:
            deleteRoad();
            break;
        default:
            DeleteAction();
            break;
    }
}

function PathAction()
{
    $sourceAndDestination = explode(':', trim(readline()));
    $source_id = (int) $sourceAndDestination[0];
    $destination_id = (int) $sourceAndDestination[1];
    global $db;

    if (!isset($db['cities'][$source_id]) || !isset($db['cities'][$destination_id])) {
        echo "City with id={$source_id} or City with id={$destination_id} not found!" . PHP_EOL;
        return;
    }

    $paths = findPaths($source_id, $destination_id);

    foreach ($paths as $path) {
        $time = secondsToTime(($path['length'] / $path['speed_limit']) * 3600);
        echo <<<EOT
        {$db['cities'][$source_id]['name']}:{$db['cities'][$destination_id]['name']} via Road {$path['name']}: Takes {$time} 
        EOT . PHP_EOL;
    }
}

function secondsToTime($seconds)
{
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    return $dtF->diff($dtT)->format('%a:%h:%i');
}

function findPaths($source_id, $destination_id)
{
    global $db;
    $paths = [];
    foreach ($db['roads'] as $road) {

        $road['through'] = array_unique(array_merge([$road['from']], $road['through'], [$road['to']]));

        if ($road['bi_directional']) {
            $paths[] = checkBiDrirectionalRoad($road, $source_id, $destination_id);
        } else {
            $paths[] = checkNonBiDrirectionalRoad($road, $source_id, $destination_id);
        }
    }

    return array_filter($paths);
}

function checkBiDrirectionalRoad($road, $source_id, $destination_id)
{
    if (in_array($source_id, $road['through']) && in_array($destination_id, $road['through'])) {
        return $road;
    }
}

function checkNonBiDrirectionalRoad($road, $source_id, $destination_id)
{
    if (in_array($source_id, $road['through']) && in_array($destination_id, $road['through'])) {
        $sourceIndex = array_search($source_id, $road['through']);
        $destinationIndex = array_search($destination_id, $road['through']);
        if ($sourceIndex < $destinationIndex) {
            return $road;
        }
    }
}

function ExitAction()
{
    exit;
}

function handleChoice($choice, $choices)
{
    if (!is_int($choice) || !array_key_exists($choice, $choices)) {
        echo "Invalid input. Please enter 1 for more info." . PHP_EOL;
    }
}

function printMainMenu($mainMenu)
{
    echo "Main Menu - Select an action:" . PHP_EOL;
    foreach ($mainMenu as $key => $value) {
        echo $key . '. ' . $value . PHP_EOL;
    }
}

do {
    global $mainMenu;
    printMainMenu($mainMenu);

    $choice = (int) readline();

    handleChoice($choice, $mainMenu);

    if (is_int($choice) && array_key_exists($choice, $mainMenu)) {
        $method = $mainMenu[$choice] . 'Action';
        $method();
    }

} while ($choice != EXIT_CHOICE);
