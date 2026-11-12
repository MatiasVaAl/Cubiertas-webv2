<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationAudit;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Services\AdministrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function index()
    {
        return response()->view('admin.settings', ['settings' => WorkshopSetting::current(), 'users' => User::orderBy('name')->get(), 'audits' => OperationAudit::with('actor')->latest('id')->paginate(15)])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, AdministrationService $admin)
    {
        $data = $request->validate([
            'formula_confirmed' => 'nullable|boolean',
            'contact' => 'nullable|array:address,hours,phone,email,about,whatsapp,instagram,facebook,map_url', 'contact.*' => 'nullable|string|max:3000', 'contact.email' => 'nullable|email|max:255',
            'contact.whatsapp' => 'nullable|url:https|max:1000', 'contact.instagram' => 'nullable|url:https|max:1000',
            'contact.facebook' => 'nullable|url:https|max:1000', 'contact.map_url' => 'nullable|url:https|max:1000',
            'bank_details' => 'nullable|array:holder,rut,bank,account_type,account_number,email', 'bank_details.*' => 'nullable|string|max:255', 'bank_details.email' => 'nullable|email|max:255',
            'payment_instructions' => 'nullable|string|max:3000',
            'dimension_limits' => 'nullable|array:length_cm_min,length_cm_max,width_cm_min,width_cm_max', 'dimension_limits.*' => 'nullable|numeric|decimal:0,2|gt:0|max:9999999999',
        ]);
        $limits = $data['dimension_limits'] ?? [];
        foreach (['length_cm', 'width_cm'] as $dimension) {
            if (isset($limits[$dimension.'_min'], $limits[$dimension.'_max']) && bccomp((string) $limits[$dimension.'_min'], (string) $limits[$dimension.'_max'], 2) > 0) {
                throw ValidationException::withMessages(['dimension_limits' => 'El mínimo de una medida no puede superar su máximo.']);
            }
        }
        $settings = WorkshopSetting::current();
        $settings->id = 1;
        $admin->save($settings, [...$data, 'formula_confirmed' => $request->boolean('formula_confirmed')], $request->user());

        return back()->with('status', 'Configuración actualizada.');
    }

    public function user(Request $request, AdministrationService $admin, ?User $user = null)
    {
        $user ??= new User;
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)], 'password' => [$user->exists ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->numbers()], 'role' => 'required|in:admin,worker', 'active' => 'nullable|boolean', 'can_verify_payments' => 'nullable|boolean']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $admin->saveUser($user, [...$data, 'active' => $request->boolean('active'), 'can_verify_payments' => $request->boolean('can_verify_payments')], $request->user());

        return back()->with('status', 'Usuario actualizado.');
    }
}
