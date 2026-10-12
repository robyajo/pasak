<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $contacts = Contact::latest()->paginate(10);

        return view('contacts.index', compact('contacts'));
    }

    public function create(): View
    {
        return view('contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:contacts,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $contact = Contact::create($validated);

        return redirect()->route('contacts.show', $contact)
            ->with('status', 'Kontak berhasil ditambahkan.');
    }

    public function show(Contact $contact): View
    {
        return view('contacts.show', compact('contact'));
    }

    public function edit(Contact $contact): View
    {
        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:contacts,email,'.$contact->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($contact);
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $contact->update($validated);

        return redirect()->route('contacts.show', $contact)
            ->with('status', 'Kontak berhasil diperbarui.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->deleteAvatar($contact);
        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('status', 'Kontak berhasil dihapus.');
    }

    private function deleteAvatar(Contact $contact): void
    {
        if ($contact->avatar !== null && $contact->avatar !== '') {
            Storage::disk('public')->delete($contact->avatar);
        }
    }
}
