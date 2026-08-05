<?php

namespace App\Http\Controllers;

use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $orders = Order::query()->where('user_id', $user->id)->latest()->take(5)->get();

        return view('storefront.account.index', compact('user', 'orders'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        Auth::user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password updated.');
    }

    public function addresses(): View
    {
        $addresses = Auth::user()->addresses()->latest()->get();

        return view('storefront.account.addresses', compact('addresses'));
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['user_id'] = Auth::id();
        $data['type'] = 'shipping';
        $data['country'] = config('aura.country', 'LB');
        $data['is_default'] = $request->boolean('is_default');

        if ($data['is_default']) {
            CustomerAddress::query()->where('user_id', Auth::id())->update(['is_default' => false]);
        }

        CustomerAddress::query()->create($data);

        return back()->with('success', 'Address saved.');
    }

    public function destroyAddress(CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === Auth::id(), 403);
        $address->delete();

        return back()->with('success', 'Address removed.');
    }
}
