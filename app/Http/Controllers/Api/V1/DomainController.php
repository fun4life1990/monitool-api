<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\HttpMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\StoreDomainRequest;
use App\Http\Requests\Domain\UpdateDomainRequest;
use App\Http\Resources\DomainResource;
use App\Jobs\CheckDomainJob;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class DomainController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $domains = $request->user()
            ->domains()
            ->orderByDesc('id')
            ->paginate(20);

        return DomainResource::collection($domains);
    }

    public function store(StoreDomainRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['method'] = $data['method'] ?? HttpMethod::HttpGet->value;

        $domain = $request->user()->domains()->create($data);

        return (new DomainResource($domain))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Domain $domain): DomainResource
    {
        $this->authorize('view', $domain);

        return new DomainResource($domain);
    }

    public function update(UpdateDomainRequest $request, Domain $domain): DomainResource
    {
        $this->authorize('update', $domain);

        $domain->update($request->validated());

        return new DomainResource($domain);
    }

    public function destroy(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('delete', $domain);

        $domain->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function check(Request $request, Domain $domain): JsonResponse
    {
        $this->authorize('view', $domain);

        CheckDomainJob::dispatch($domain->id);

        return response()->json(['message' => 'Check queued.'], Response::HTTP_ACCEPTED);
    }
}
