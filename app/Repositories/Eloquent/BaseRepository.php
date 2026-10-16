<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model) {}

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->model->with($relations)->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->select($columns)->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(int $id, array $columns = ['*'], array $relations = []): Model
    {
        return $this->model->with($relations)->select($columns)->findOrFail($id);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $payload): Model
    {
        return $this->model->create($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $payload): bool
    {
        $record = $this->findById($id);

        if (! $record) {
            return false;
        }

        return $record->update($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $record = $this->findById($id);

        if (! $record) {
            return false;
        }

        return (bool) $record->delete();
    }
}
