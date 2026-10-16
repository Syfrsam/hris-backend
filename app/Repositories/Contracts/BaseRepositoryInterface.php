<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    /**
     * Get all models.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function all(array $columns = ['*'], array $relations = []): Collection;

    /**
     * Get paginated models.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator;

    /**
     * Find a model by ID.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Find a model by ID or throw an exception.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function findOrFail(int $id, array $columns = ['*'], array $relations = []): Model;

    /**
     * Create a new model instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Model;

    /**
     * Update an existing model.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $id, array $payload): bool;

    /**
     * Delete a model by ID.
     */
    public function delete(int $id): bool;
}
