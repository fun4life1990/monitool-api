<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DomainCheckResource;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DomainCheckController extends Controller
{
    public function index(Request $request, Domain $domain): AnonymousResourceCollection
    {
        $this->authorize('view', $domain);

        $checks = $domain->checks()
            ->orderByDesc('checked_at')
            ->paginate(50);

        return DomainCheckResource::collection($checks);
    }
}
