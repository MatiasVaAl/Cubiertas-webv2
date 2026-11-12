<x-layout title="Acceso del equipo · Cubiertas Temuco">
    <section class="card narrow"><p class="eyebrow">ÁREA PRIVADA</p><h1>Acceso del equipo</h1><p>Ingresa con tu cuenta autorizada.</p>
        <form method="POST" action="{{ route('login.store') }}" class="form-stack">@csrf
            <label>Correo electrónico<input name="email" type="email" autocomplete="username" required value="{{ old('email') }}"></label>
            <label>Contraseña<input name="password" type="password" autocomplete="current-password" required></label>
            <button class="button">Ingresar</button>
        </form>
    </section>
</x-layout>
