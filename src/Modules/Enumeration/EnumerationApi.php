<?php

declare(strict_types=1);

namespace App\Modules\Enumeration;

use App\Modules\Router\Request;
use App\Modules\Router\Response;

class EnumerationApi
{
    private EnumerationService $service;

    public function __construct()
    {
        $this->service = new EnumerationService();
    }

    /** GET /enumerations */
    public function list(Request $request): void
    {
        $isActive = $request->get('is_active');
        Response::success($this->service->list(
            $request->get('type'),
            $isActive !== null ? (bool)(int) $isActive : null,
            (string) $request->get('sort_by', 'sort_order'),
            (string) $request->get('sort_dir', 'ASC'),
        ));
    }

    /** GET /enumerations/types */
    public function types(Request $request): void
    {
        Response::success($this->service->types());
    }

    /** GET /enumerations/:id */
    public function get(Request $request, array $params): void
    {
        Response::success($this->service->get((int) $params['id']));
    }

    /** POST /enumerations */
    public function create(Request $request): void
    {
        $id = $this->service->create(
            trim((string) $request->get('type', '')),
            trim((string) $request->get('code', '')),
            trim((string) $request->get('label', '')),
            [
                'value'      => $request->get('value'),
                'sort_order' => $request->get('sort_order', 0),
                'is_active'  => $request->get('is_active', 1),
            ],
        );
        Response::created(['id' => $id], 'Enumeration created');
    }

    /** PATCH /enumerations/:id */
    public function update(Request $request, array $params): void
    {
        $this->service->update((int) $params['id'], [
            'type'       => $request->get('type'),
            'code'       => $request->get('code'),
            'label'      => $request->get('label'),
            'value'      => $request->get('value'),
            'sort_order' => $request->get('sort_order'),
            'is_active'  => $request->get('is_active'),
        ]);
        Response::success(null, 'Enumeration updated');
    }

    /** PUT /enumerations/:id */
    public function replace(Request $request, array $params): void
    {
        $this->service->replace(
            (int) $params['id'],
            trim((string) $request->get('type', '')),
            trim((string) $request->get('code', '')),
            trim((string) $request->get('label', '')),
            [
                'value'      => $request->get('value'),
                'sort_order' => $request->get('sort_order', 0),
                'is_active'  => $request->get('is_active', 1),
            ],
        );
        Response::success(null, 'Enumeration replaced');
    }

    /** DELETE /enumerations/:id */
    public function delete(Request $request, array $params): void
    {
        $this->service->delete((int) $params['id']);
        Response::success(null, 'Enumeration deleted');
    }
}
