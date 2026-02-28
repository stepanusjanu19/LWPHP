<?php

namespace Kei\Lwphp\Controller;

use Kei\Lwphp\Base\Controller as BaseController;
use Kei\Lwphp\Repository\JobRepository;
use Kei\Lwphp\Service\JobQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Core\View;
use Nyholm\Psr7\Response;

/**
 * JobController — HTTP adapter for the background job queue.
 *
 * Extends Base\Controller for json()/parseBody()/paginate() helpers.
 *
 * POST   /jobs/dispatch    → push a job to the queue
 * GET    /jobs             → list all jobs (paginated)
 * GET    /jobs/{id}        → single job detail
 * DELETE /jobs/{id}        → cancel a pending job
 * DELETE /jobs/purge       → purge done/failed jobs
 */
class JobController extends BaseController
{
    public function __construct(
        private readonly JobQueue $queue,
        private readonly JobRepository $repo,
        \Kei\Lwphp\Core\View $view,
    ) {
        parent::__construct();
        $this->setView($view);
    }

    // POST /jobs/dispatch
    public function dispatch(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $body = $this->parseBody($request);
        $name = trim((string) ($body['job'] ?? ''));

        if ($name === '') {
            return $this->unprocessable('Field "job" is required.', ['available' => JobQueue::JOBS]);
        }

        try {
            $job = $this->queue->dispatch($name, $body['payload'] ?? []);
            return $this->created(
                $job->toArray(),
                "Job '{$name}' queued (id={$job->getId()}). Run `php bin/worker.php` to process."
            );
        } catch (\InvalidArgumentException $e) {
            return $this->unprocessable($e->getMessage(), ['available' => JobQueue::JOBS]);
        }
    }

    // GET /jobs[?status=pending&page=1&limit=25]
    public function index(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;

        $all = $status
            ? array_filter($this->queue->list(), fn($j) => $j->getStatus() === $status)
            : $this->queue->list();

        ['page' => $page, 'limit' => $limit] = $this->paginationParams($request);

        $page = $this->paginate(
            array_map(fn($j) => $j->toArray(), array_values($all)),
            $page,
            $limit
        );

        if (str_contains($request->getHeaderLine('Accept'), 'text/html')) {
            return $this->render('cms/jobs', [
                'items' => $page['data'],
                'pagination' => ['total' => $page['pagination']['total'], 'page' => $page['pagination']['page'], 'pages' => $page['pagination']['pages']],
                'stats' => $this->queue->stats()
            ]);
        }

        return $this->json(200, array_merge($page, ['stats' => $this->queue->stats()]));
    }

    // GET /jobs/{id}
    public function show(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $job = $this->repo->findById((int) $vars['id']);
        return $job
            ? $this->json(200, ['data' => $job->toArray()])
            : $this->notFound('Job', (int) $vars['id']);
    }

    // DELETE /jobs/{id}
    public function destroy(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $cancelled = $this->repo->cancel((int) $vars['id']);
        return $cancelled
            ? $this->json(200, ['message' => 'Job cancelled.'])
            : $this->json(400, ['error' => 'Cannot cancel — job not found or not pending.']);
    }

    // DELETE /jobs/purge
    public function purge(ServerRequestInterface $request, array $vars): ResponseInterface
    {
        $days = (int) ($request->getQueryParams()['days'] ?? 0);
        $removed = $this->repo->purgeOld($days);
        $scope = $days === 0 ? 'all' : "older than {$days} days";
        return $this->json(200, [
            'message' => "Purged {$removed} done/failed job(s) ({$scope}).",
            'removed' => $removed,
        ]);
    }
}