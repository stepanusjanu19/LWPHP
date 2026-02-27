<?php

namespace Kei\Lwphp\Service;

/**
 * HeavyJobService — CPU-bound task simulations for benchmarking.
 *
 * Each method intentionally uses CPU to demonstrate the benefit
 * of async Fiber / parallel fork over sequential synchronous execution.
 */
class HeavyJobService
{
    /**
     * Find all prime numbers up to $limit (Sieve of Eratosthenes).
     * ~50ms for limit=500_000 on modern hardware.
     */
    public function sievePrimes(int $limit = 500_000): array
    {
        $sieve = array_fill(2, $limit - 1, true);
        for ($i = 2; $i <= (int) sqrt($limit); $i++) {
            if ($sieve[$i] ?? false) {
                for ($j = $i * $i; $j <= $limit; $j += $i) {
                    $sieve[$j] = false;
                }
            }
        }
        return array_keys(array_filter($sieve));
    }

    /**
     * Matrix multiplication of two NxN matrices (pure PHP).
     * ~30ms for N=150 on modern hardware.
     */
    public function matrixMultiply(int $n = 150): array
    {
        $a = $b = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $a[$i][$j] = mt_rand(1, 100);
                $b[$i][$j] = mt_rand(1, 100);
            }
        }

        $c = array_fill(0, $n, array_fill(0, $n, 0));
        for ($i = 0; $i < $n; $i++) {
            for ($k = 0; $k < $n; $k++) {
                for ($j = 0; $j < $n; $j++) {
                    $c[$i][$j] += $a[$i][$k] * $b[$k][$j];
                }
            }
        }
        return ['size' => $n, 'c00' => $c[0][0]]; // return corner for validation
    }

    /**
     * SHA-256 hash chain — I/O-light pure CPU work.
     * ~20ms for iterations=50_000.
     */
    public function hashChain(int $iterations = 50_000): string
    {
        $hash = 'lwphp-benchmark-seed';
        for ($i = 0; $i < $iterations; $i++) {
            $hash = hash('sha256', $hash . $i);
        }
        return $hash;
    }

    /**
     * Fibonacci using memoized recursion (stress-tests call stack).
     */
    public function fibonacci(int $n = 35): int
    {
        $memo = [];
        $fib = function (int $n) use (&$fib, &$memo): int {
            if ($n <= 1)
                return $n;
            return $memo[$n] ??= $fib($n - 1) + $fib($n - 2);
        };
        return $fib($n);
    }

    /**
     * Sorting stress: generate and sort a large random array.
     */
    public function sortStress(int $size = 100_000): int
    {
        $arr = [];
        for ($i = 0; $i < $size; $i++) {
            $arr[] = mt_rand(0, PHP_INT_MAX);
        }
        sort($arr);
        return $arr[0]; // return min for validation
    }

    /**
     * All 5 heavy jobs packaged as named closures for the job dispatcher.
     *
     * @return array<string, \Closure>
     */
    public function allJobs(): array
    {
        return [
            'primes' => fn() => ['count' => count($this->sievePrimes()), 'job' => 'sieve_primes'],
            'matrix' => fn() => $this->matrixMultiply(),
            'hash' => fn() => ['hash' => substr($this->hashChain(), 0, 16), 'job' => 'hash_chain'],
            'fibonacci' => fn() => ['fib35' => $this->fibonacci(35), 'job' => 'fibonacci'],
            'sort' => fn() => ['min' => $this->sortStress(), 'job' => 'sort_stress'],
        ];
    }
}
