<?php
require __DIR__ . '/vendor/autoload.php';

use Kei\Lwphp\Core\App;

$app = new App();
$container = $app->getContainer();
$em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);

$features = [
    [
        'title' => 'Fast Core',
        'desc' => 'Built with efficiency in mind. Minimal overhead and optimized DI container for sub-millisecond resolutions.',
        'icon' => '<svg style="width:24px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>'
    ],
    [
        'title' => 'Livewire + HTMX',
        'desc' => 'Fully reactive components without writing a single line of complex JavaScript. Stay in PHP/Twig.',
        'icon' => '<svg style="width:24px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>'
    ],
    [
        'title' => 'Secure by Design',
        'desc' => 'Advanced signature-based authentication and CSRF protection built into the core.',
        'icon' => '<svg style="width:24px" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>'
    ]
];

foreach ($features as $f) {
    $entity = new \Kei\Lwphp\Entity\LandingFeature();
    $entity->setTitle($f['title']);
    $entity->setDescription($f['desc']);
    $entity->setIcon($f['icon']);
    $em->persist($entity);
}

$em->flush();
echo "Landing Features seeded.\n";