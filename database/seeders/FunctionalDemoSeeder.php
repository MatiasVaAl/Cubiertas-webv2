<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Services\CartService;
use App\Services\HistoryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\QuoteService;
use App\Services\TrackingService;
use Database\Seeders\Support\DemonstrationReceipt;
use Illuminate\Database\Seeder;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class FunctionalDemoSeeder extends Seeder
{
    private array $createdFiles = [];

    public function run(): void
    {
        if (! app()->runningInConsole() || ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los escenarios ficticios solo pueden cargarse por consola en local/testing.');
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Los escenarios requieren PostgreSQL.');
        }

        $this->createdFiles = [];
        $originalSession = app('session');
        $session = new Store('demo-seeder', new ArraySessionHandler(120));
        $session->start();
        Session::swap($session);

        try {
            DB::transaction(function () {
                DB::select("SELECT pg_advisory_xact_lock(hashtext('cubiertas-functional-demo-v1'))");
                $path = $this->reportPath();
                $report = $this->readReport($path);
                $this->callSilent(DemonstrationSeeder::class);
                $operations = $this->demoUser('operations', 'Operaciones DEMO', 'worker', false, $report);
                $verifier = $this->demoUser('payments', 'Verificación de pagos DEMO', 'worker', true, $report);
                $this->demoUser('admin', 'Administración DEMO', 'admin', true, $report);

                foreach ($this->scenarios() as $index => $scenario) {
                    $reference = 'DEMO-FLOW-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                    $marker = '['.$reference.'] DATOS FICTICIOS PARA PRUEBAS; SIN VALIDEZ COMERCIAL.';
                    $quote = Quote::where('notes', $marker)->first();
                    if (! $quote) {
                        $report['scenarios'][$reference] = $this->createScenario($index, $scenario, $marker, $operations, $verifier);
                    } elseif (! isset($report['scenarios'][$reference])) {
                        // No renovar credenciales ni modificar escenarios existentes al repetir el seeder.
                        $report['scenarios'][$reference] = ['number' => $quote->number, 'scenario' => $scenario['label'], 'access_note' => 'Recuperar el código desde el informe original o renovarlo desde el panel.'];
                    }
                }

                Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            });
        } catch (Throwable $exception) {
            foreach ($this->createdFiles as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        } finally {
            Session::swap($originalSession);
        }

        $this->command?->info('Datos DEMO preparados: 10 acabados y 14 escenarios. Los existentes se conservan.');
        $report = $this->readReport($this->reportPath());
        $rows = [];
        foreach ($report['scenarios'] as $reference => $entry) {
            $rows[] = [$reference, $entry['number'], $entry['scenario']];
        }
        $this->command?->table(['Referencia', 'Solicitud', 'Escenario de muestra'], $rows);
        $this->command?->info('Accesos privados: '.Storage::disk('local')->path($this->reportPath()));
    }

    private function reportPath(): string
    {
        return 'demo/'.rawurlencode(DB::connection()->getDatabaseName()).'/accesos.json';
    }

    private function readReport(string $path): array
    {
        if (! Storage::disk('local')->exists($path)) {
            return ['notice' => 'SOLO DESARROLLO. Datos ficticios; no transferir dinero.', 'created_at' => now()->toIso8601String(), 'users' => [], 'scenarios' => []];
        }
        try {
            $report = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('El informe privado de demostración no es válido; consérvalo y revísalo antes de continuar.');
        }
        if (! is_array($report) || ! is_array($report['users'] ?? null) || ! is_array($report['scenarios'] ?? null)) {
            throw new RuntimeException('El informe privado de demostración no tiene el formato esperado.');
        }

        return $report;
    }

    private function demoUser(string $key, string $name, string $role, bool $verify, array &$report): User
    {
        $email = $key.'.demo@example.invalid';
        $user = User::where('email', $email)->first();
        if (! $user) {
            $password = Str::password(24);
            $user = new User;
            $user->forceFill(['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role, 'active' => true, 'can_verify_payments' => $verify])->save();
            $report['users'][$key] = ['email' => $email, 'password' => $password, 'role' => $role, 'can_verify_payments' => $verify];
        }

        return $user;
    }

    private function scenarios(): array
    {
        return [
            ['label' => 'Acabado pendiente de revisión', 'stage' => 'pending'],
            ['label' => 'Material especial por evaluar', 'stage' => 'pending', 'special' => true],
            ['label' => 'Transporte pendiente de costo', 'stage' => 'transport_pending', 'transport' => true],
            ['label' => 'Esperando información del cliente', 'stage' => 'information', 'special' => true],
            ['label' => 'Propuesta conjunta esperando aceptación', 'stage' => 'proposal', 'multiple' => true],
            ['label' => 'Aceptada y pendiente de transferencia', 'stage' => 'accepted'],
            ['label' => 'Comprobante en verificación', 'stage' => 'receipt'],
            ['label' => 'Pago observado', 'stage' => 'observed'],
            ['label' => 'Pagado y pendiente de fabricación', 'stage' => 'paid'],
            ['label' => 'En fabricación y en espera', 'stage' => 'hold'],
            ['label' => 'Listo para retiro', 'stage' => 'ready'],
            ['label' => 'Entregado al transportista', 'stage' => 'dispatched', 'transport' => true],
            ['label' => 'Retirado por el cliente', 'stage' => 'delivered'],
            ['label' => 'Transporte con entrega confirmada', 'stage' => 'confirmed', 'transport' => true],
        ];
    }

    private function createScenario(int $index, array $scenario, string $marker, User $operations, User $verifier): array
    {
        $cart = app(CartService::class);
        $quotes = app(QuoteService::class);
        session()->forget('cart');
        $product = Product::where('code', 'like', 'DEMO-%')->where('demonstration', true)->where('available', true)->orderBy('code')->skip($index % 8)->first();
        $special = ($scenario['special'] ?? false) || ! $product;
        $input = [
            'kind' => $special ? 'special' : 'standard', 'product_id' => $product?->id,
            'material_name' => $special ? 'Material especial DEMO '.($index + 1) : null,
            'material_description' => 'Muestra ficticia para evaluación; no acredita disponibilidad real.',
            'length_cm' => $this->dimension('length_cm', (string) (150 + $index * 5)),
            'width_cm' => $this->dimension('width_cm', (string) (45 + $index % 4 * 5)),
            'quantity' => 1 + $index % 3, 'notes' => 'DEMO · '.$scenario['label'],
        ];
        $cart->add($input);
        if ($scenario['multiple'] ?? false) {
            $cart->add($input); // Debe agruparse en la misma línea.
            $cart->add([...$input, 'kind' => 'special', 'material_name' => 'Alternativa especial DEMO', 'quantity' => 1]);
        }
        $result = $quotes->submit([
            'name' => 'Cliente DEMO '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).' · '.$scenario['label'],
            'phone' => 'DEMO-NO-LLAMAR-'.($index + 1), 'email' => 'cliente'.($index + 1).'.demo@example.invalid',
            'delivery_method' => ($scenario['transport'] ?? false) ? 'transport' : 'pickup',
            'city' => ($scenario['transport'] ?? false) ? 'Comuna ficticia DEMO' : null,
            'carrier' => ($scenario['transport'] ?? false) ? 'Transportista ficticio DEMO' : null,
            'destination' => ($scenario['transport'] ?? false) ? 'Destino de prueba, sin dirección real' : null,
            'notes' => $marker,
        ]);
        $quote = $result['quote'];
        $entry = ['number' => $quote->number, 'scenario' => $scenario['label'], 'code' => $result['token'], 'private_url' => route('tracking.lookup').'#number='.$quote->number.'&code='.$result['token']];
        $stage = $scenario['stage'];
        if ($stage === 'pending') {
            return $entry;
        }
        if ($stage === 'information') {
            $quotes->changeStatus($quote, 'awaiting_information', 'DEMO · Se necesitan aclaraciones ficticias sobre medidas y material.', $operations);

            return $entry;
        }
        foreach ($quote->lines()->get() as $line) {
            $quotes->resolveLine($quote, $line, 10000 + $index * 1000, 'DEMO · Precio y factibilidad simulados, sin validez comercial.', $operations);
        }
        if ($stage === 'transport_pending') {
            return $entry;
        }
        if ($scenario['transport'] ?? false) {
            $quotes->confirmTransport($quote, 5000, 'DEMO · Costo y condiciones ficticios para probar el transporte.', $operations);
        }
        $version = $quotes->issue($quote, 'DEMO · Propuesta ficticia para pruebas; no corresponde a una oferta real.', $operations);
        if ($stage === 'proposal') {
            return $entry;
        }
        app(TrackingService::class)->authenticate($quote->number, $result['token']);
        $order = $quotes->accept($quote, $version->version);
        if ($stage === 'accepted') {
            return $entry;
        }
        $payment = $this->syntheticPayment($order);
        if ($stage === 'receipt') {
            return $entry;
        }
        app(PaymentService::class)->verify($payment, $verifier, $stage !== 'observed', 'DEMO · Revisión simulada, sin transferencia ni ingreso bancario real.', $stage !== 'observed');
        if (in_array($stage, ['observed', 'paid'], true)) {
            return $entry;
        }
        $orders = app(OrderService::class);
        $orders->transition($order, 'start', [], $operations);
        if ($stage === 'hold') {
            $orders->transition($order, 'hold', ['reason' => 'DEMO · Pausa ficticia para probar espera y reanudación.'], $operations);

            return $entry;
        }
        $orders->transition($order, 'finish', [], $operations);
        if ($scenario['transport'] ?? false) {
            $orders->transition($order, 'dispatch', ['carrier' => 'Transportista ficticio DEMO', 'destination' => 'Destino de prueba, sin dirección real', 'dispatched_at' => now()->toDateString(), 'notes' => 'DEMO · Despacho simulado; sin número de seguimiento real.'], $operations);
            if ($stage === 'confirmed') {
                $orders->transition($order, 'confirm_delivery', ['evidence' => 'DEMO · Evidencia ficticia de recepción para probar la transición.'], $operations);
            }
        } else {
            $orders->transition($order, 'ready_pickup', [], $operations);
            if ($stage === 'delivered') {
                $orders->transition($order, 'deliver', [], $operations);
            }
        }

        return $entry;
    }

    private function dimension(string $key, string $sample): string
    {
        $limits = WorkshopSetting::current()->dimension_limits ?? [];
        foreach (['min' => -1, 'max' => 1] as $limit => $comparison) {
            if (isset($limits[$key.'_'.$limit]) && bccomp($sample, (string) $limits[$key.'_'.$limit], 2) === $comparison) {
                $sample = (string) $limits[$key.'_'.$limit];
            }
        }

        return bcadd($sample, '0', 2);
    }

    private function syntheticPayment(Order $order): Payment
    {
        // Fixture exclusivo del seeder: no simular una carga bancaria ni configurar una cuenta.
        // Las verificaciones y transiciones posteriores sí usan los servicios comerciales.
        $contents = DemonstrationReceipt::pdf($order->quote->number);
        $path = 'demo/receipts/'.$order->quote->id.'-'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, $contents);
        $this->createdFiles[] = $path;
        $payment = $order->payments()->create(['expected_clp' => $order->total_clp, 'path' => $path, 'sha256' => hash('sha256', $contents), 'mime' => 'application/pdf', 'observations' => 'DEMO · Comprobante sintético sin validez de pago.']);
        $order->update(['payment_status' => 'under_review']);
        app(HistoryService::class)->record($order->quote, 'demo_receipt_created', 'DEMO · Comprobante sintético creado para pruebas; no acredita dinero recibido.');

        return $payment;
    }
}
