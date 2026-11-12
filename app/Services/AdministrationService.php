<?php

namespace App\Services;

use App\Models\OperationAudit;
use App\Models\Product;
use App\Models\User;
use App\Models\WidthRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdministrationService
{
    public function save(Model $model, array $data, User $actor): Model
    {
        Gate::forUser($actor)->authorize('manage-settings');

        return DB::transaction(function () use ($model, $data, $actor) {
            $action = $model->exists ? 'updated' : 'created';
            $model->fill($data)->save();
            $this->audit($model, $action, array_keys($data), $actor);

            return $model;
        });
    }

    public function saveRate(WidthRate $rate, array $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage-settings');
        DB::transaction(function () use ($rate, $data, $actor) {
            Product::lockForUpdate()->findOrFail($data['product_id']);
            if ($rate->exists && $rate->product_id !== (int) $data['product_id']) {
                throw ValidationException::withMessages(['product_id' => 'Una tarifa existente no se cambia de producto. Desactívala y crea otra.']);
            }
            if ($data['active'] && WidthRate::where('product_id', $data['product_id'])->where('active', true)->when($rate->exists, fn ($q) => $q->where('id', '<>', $rate->id))->where('min_width_cm', '<', $data['max_width_cm'])->where('max_width_cm', '>', $data['min_width_cm'])->exists()) {
                throw ValidationException::withMessages(['min_width_cm' => 'El rango se superpone con otra tarifa activa. Los límites son [mínimo, máximo).']);
            }
            $this->save($rate, $data, $actor);
        });
    }

    public function saveUser(User $user, array $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage-settings');
        DB::transaction(function () use ($user, $data, $actor) {
            $admins = User::where('role', 'admin')->where('active', true)->orderBy('id')->lockForUpdate()->get();
            if ($user->exists && $user->isAdministrator() && ($data['role'] !== 'admin' || ! $data['active']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Debe mantenerse al menos un administrador activo.']);
            }
            $action = $user->exists ? 'updated' : 'created';
            $user->forceFill($data)->save();
            $this->audit($user, $action, array_keys($data), $actor);
        });
    }

    public function audit(Model $model, string $action, array $fields, User $actor): void
    {
        // Solo nombres de campos: nunca valores bancarios, contraseñas ni secretos.
        OperationAudit::create(['actor_id' => $actor->id, 'action' => $action, 'entity_type' => class_basename($model), 'entity_id' => $model->getKey(), 'changed_fields' => $fields]);
    }
}
