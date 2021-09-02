<?php
require  __DIR__ . '/vendor/autoload.php';


$routers = [
  ['host' => '', 'username'=> '', 'password' => '', 'port' => 55055]
];

$lastStates = [];
$currentStates = [];

//Load last state
if(file_exists(__DIR__ . '/last_state.json')) {
    $lastStates = json_decode(file_get_contents(__DIR__ . '/last_state.json'), true);
}

//Load current states from routerAPI
foreach ($routers as $router) {
    $api = new RouterosAPI;
    $api->port = $router['port'];
    if(!$api->connect($router['host'], $router['username'], $router['password'])) {
        echo "Error connecting to {$router['host']} \n";
        continue;
    }
    $peers = $api->comm('/routing/bgp/peer/print');
    foreach ($peers as $peer) {
        $currentStates["{$router['host']}{$peer['remote-address']}"] = [
            'router' => $router['host'],
            'name' => $peer['name'],
            'local-address' => isset($peer['local-address']) ? $peer['local-address'] : null,
            'remote-address' => $peer['remote-address'],
            'remote-as' => $peer['remote-as'],
            'disabled' => $peer['disabled'],
            'established' =>  isset($peer['established']) ? $peer['established'] : null,
        ];
    }
}

//Compare sessions
foreach ($currentStates as $key=>$cur) {
    if(
        //Session latest exist end state was changed
        isset($lastStates[$key]) &&
        $lastStates[$key]['established'] != $cur['established']
    ) {
        sendNotify(
            $lastStates[$key],
            $cur,
            $cur['established'] ? 'up' : 'down'
        );
    } elseif (
        //New session detected
        !isset($lastStates[$key])
    ) {
        sendNotify(
            null,
            $cur,
            'peer-added'
        );
    }
}
foreach ($lastStates as $key=>$lastState) {
    if(!isset($currentStates[$key])) {
        sendNotify($currentStates[$key], null, 'peer-removed');
    }
}

//Save current state
file_put_contents(
    __DIR__ . '/last_state.json',
    json_encode($currentStates,
        JSON_PRETTY_PRINT
    )
);

function sendNotify($old, $new, $eventType) {
    switch ($eventType) {
        case 'peer-added':
            echo "Peer with name {$new['name']} will be created\n";
            break;
        case 'peer-removed':
            echo "Peer with name {$old['name']} will be removed\n";
            break;
        case 'up':
            echo "Peer with name {$new['name']} UP\n";
            break;
        case 'down':
            echo "Peer with name {$new['name']} DOWN\n";
            break;
    }
}