<?php
require __DIR__ . '/vendor/autoload.php';
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();

$arcs = $em->getRepository(App\Entity\NarrativeArc::class)->findAll();
foreach ($arcs as $arc) {
    echo "\n=== {$arc->getName()} ({$arc->getTendency()}) ===\n";
    echo "Steps:\n";
    print_r($arc->getSteps());
    echo "\nVariants:\n";
    print_r($arc->getVariants());
    echo "\n";
    if ($arc->getId() >= 2) break;
}
