<?php

namespace App\Http\Controllers;

use App\Services\CachedStatusPage;
use App\Services\StatusPageVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function __construct(
        protected CachedStatusPage $statusPage,
        protected StatusPageVisibility $visibility,
    ) {}

    public function index(Request $request): View
    {
        return view('status.index', $this->publicViewData($request));
    }

    public function fragment(Request $request): View
    {
        return view('status.partials.page-content', $this->publicViewData($request));
    }

    public function wallboard(Request $request): Response
    {
        return $this->wallboardResponse('status.wallboard', $request);
    }

    public function wallboardFragment(Request $request): Response
    {
        return $this->wallboardResponse('status.partials.page-content', $request);
    }

    private function publicViewData(Request $request): array
    {
        $snapshot = $this->statusPage->current();
        $visibleStatusPage = $this->visibility->filter($snapshot, $this->candidateIps($request));

        return [
            'statusPage' => $visibleStatusPage,
            'statusDebug' => $this->debug($request, $snapshot, $visibleStatusPage),
        ];
    }

    private function wallboardViewData(Request $request): array
    {
        abort_unless($this->visibility->isTrustedVisitor($this->candidateIps($request)), 403);
        $snapshot = $this->statusPage->current();

        return [
            'statusPage' => $snapshot,
            'statusDebug' => $this->debug($request, $snapshot, $snapshot),
            'wallboard' => true,
        ];
    }

    private function candidateIps(Request $request): array
    {
        return $this->visibility->candidateIps(
            $request->ip(),
            $request->headers->get('X-Real-IP'),
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('X-Forwarded-For'),
            $request->server('REMOTE_ADDR'),
        );
    }

    private function debug(Request $request, array $snapshot, array $visibleStatusPage): array
    {
        return $this->visibility->debug(
            $snapshot,
            $visibleStatusPage,
            $request->ip(),
            $request->headers->get('X-Real-IP'),
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('X-Forwarded-For'),
        );
    }

    private function wallboardResponse(string $view, Request $request): Response
    {
        return response()
            ->view($view, $this->wallboardViewData($request))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
