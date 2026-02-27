#!/usr/bin/env php
<?php
/**
 * Benchmark Master Runner
 * Runs all benchmark tests and prints a comparison table.
 *
 * Usage:  php benchmark/run_all.php
 */

define('BENCHMARK_DIR', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/config/helpers.php';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function hr(string $title = ''): void
{
    $line = str_repeat('─', 70);
    echo $title !== '' ? "\n┌{$line}┐\n│  \033[1m{$title}\033[0m\n└{$line}┘\n" : "\n{$line}\n";
}

function row(string $label, string $value, string $color = '0'): void
{
    printf("  \033[{$color}m%-38s\033[0m %s\n", $label, $value);
}

function ms(float $v): string
{
    return sprintf('%8.2f ms', $v);
}
function pct(float $base, float $v): string
{
    if ($base <= 0)
        return '      —';
    $p = (($base - $v) / $base) * 100;
    $color = $p > 0 ? '32' : '31';
    return sprintf("\033[{$color}m%+.1f%%\033[0m faster", $p);
}

// ─── 01: Framework Bootstrap ──────────────────────────────────────────────────
hr('01 · Framework Bootstrap Time');

$trials = 5;
$boots = [];
for ($i = 0; $i < $trials; $i++) {
    $t = hrtime(true);
    $config = new \Kei\Lwphp\Core\ConfigLoader();
    $boots[] = (hrtime(true) - $t) / 1e6;
}
$avgBoot = array_sum($boots) / count($boots);

row('ConfigLoader avg boot', ms($avgBoot), '33');
row('Trials', (string) $trials);

// Also time App full bootstrap
$t = hrtime(true);
ob_start();
$app = new \Kei\Lwphp\Core\App();
ob_end_clean();
$appBoot = (hrtime(true) - $t) / 1e6;

row('Full App (DI) build', ms($appBoot), '33');

// ─── 02: Route Dispatcher ────────────────────────────────────────────────────
hr('02 · Route Dispatch (10 000 iterations)');

use FastRoute\Dispatcher;
$router = new \Kei\Lwphp\Routing\Router($config);

$iterations = 10_000;
$t = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $info = $router->dispatch('GET', '/tasks');
    $info = $router->dispatch('GET', '/tasks/1');
    $info = $router->dispatch('POST', '/benchmark/sync');
    $info = $router->dispatch('GET', '/nonexistent');
}
$dispatchMs = (hrtime(true) - $t) / 1e6;

row("Dispatches (×{$iterations}×4 routes)", ms($dispatchMs));
row('Per dispatch', ms($dispatchMs / ($iterations * 4)));

// ─── 03: CPU-Heavy Jobs ───────────────────────────────────────────────────────
hr('03 · CPU-Heavy Jobs');

$heavy = new \Kei\Lwphp\Service\HeavyJobService();
$jobs = $heavy->allJobs();

$jobTimes = [];
foreach ($jobs as $name => $task) {
    $t = hrtime(true);
    $task();
    $ms = (hrtime(true) - $t) / 1e6;
    $jobTimes[$name] = $ms;
    row("  {$name}", ms($ms));
}
$totalCpu = array_sum($jobTimes);
row('Total (sequential)', ms($totalCpu), '31');

// ─── 04: Sync vs Async vs Parallel ───────────────────────────────────────────
hr('04 · Sync vs Async (Fiber) vs Parallel (pcntl)');

$dispatcher = new \Kei\Lwphp\Async\JobDispatcher($config);

// Sync
$t = hrtime(true);
$syncRes = $dispatcher->runSyncBatch($heavy->allJobs());
$syncMs = (hrtime(true) - $t) / 1e6;

// Async Fiber
$t = hrtime(true);
$asyncRes = $dispatcher->runAsync($heavy->allJobs());
$asyncMs = (hrtime(true) - $t) / 1e6;

// Parallel fork
$t = hrtime(true);
$parRes = $dispatcher->runParallel($heavy->allJobs());
$parMs = (hrtime(true) - $t) / 1e6;

echo "\n";
printf("  %-22s %12s   %12s\n", '', 'Wall time', 'vs Sync');
printf("  %s\n", str_repeat('·', 50));
printf("  %-22s %s   %s\n", 'Sync (sequential)', ms($syncMs), '   (baseline)');
printf("  %-22s %s   %s\n", 'Async Fiber', ms($asyncMs), pct($syncMs, $asyncMs));
printf("  %-22s %s   %s\n", 'Parallel pcntl', ms($parMs), pct($syncMs, $parMs));

if (!function_exists('pcntl_fork')) {
    echo "\n  \033[33m⚠ pcntl not available — parallel fell back to Fiber mode\033[0m\n";
}

// ─── 05: Memory Usage ────────────────────────────────────────────────────────
hr('05 · Memory Usage');

row('Peak memory', number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');
row('Current memory', number_format(memory_get_usage(true) / 1024 / 1024, 2) . ' MB');

// ─── Summary ─────────────────────────────────────────────────────────────────
hr('Summary');

echo <<<TABLE

  ┌─────────────────────────────────────────────────────────────────┐
  │                    LWPHP Benchmark Results                      │
  ├───────────────────────────────┬─────────────┬──────────────────┤
  │ Test                          │   Wall Time  │   Notes          │
  ├───────────────────────────────┼─────────────┼──────────────────┤
TABLE;

printf("\n  │ %-29s │ %s │ %-16s │", 'ConfigLoader boot', ms($avgBoot), 'with file-cache');
printf("\n  │ %-29s │ %s │ %-16s │", 'App (DI) build', ms($appBoot), 'full bootstrap');
printf("\n  │ %-29s │ %s │ %-16s │", 'Route dispatch/req', ms($dispatchMs / ($iterations * 4)), 'per call');
printf("\n  │ %-29s │ %s │ %-16s │", '5× CPU jobs sync', ms($syncMs), 'sequential sum');
printf("\n  │ %-29s │ %s │ %-16s │", '5× CPU jobs async', ms($asyncMs), 'Fiber pool');
printf("\n  │ %-29s │ %s │ %-16s │", '5× CPU jobs parallel', ms($parMs), 'pcntl fork');

echo "\n  └───────────────────────────────┴─────────────┴──────────────────┘\n\n";
