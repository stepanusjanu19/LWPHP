<?php

namespace Kei\Lwphp\Contract;

/**
 * Generic service contract.
 * @template TInput
 * @template TOutput
 */
interface ServiceInterface
{
    /** @param TInput $data */
    public function execute(mixed $data): mixed;
}
