<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class CartCounter extends Component
{
    #[On('cart-updated')]
    public function refreshCount(): void {}

    public function render()
    {
        return view('livewire.cart-counter', ['quantity' => collect(session('cart', []))->sum('quantity')]);
    }
}
