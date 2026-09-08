<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LinkHubButton;
use App\Models\LinkHubSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LinkHubController extends Controller
{
    public function edit(): View
    {
        $settings = LinkHubSetting::current();

        return view('admin.link-hub.edit', [
            'settings' => $settings,
            'buttons' => LinkHubButton::query()->ordered()->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headline' => ['required', 'string', 'max:255'],
            'headline_ar' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'body_ar' => ['nullable', 'string', 'max:5000'],
            'buttons' => ['nullable', 'array'],
            'buttons.*.id' => ['nullable', 'integer', 'exists:link_hub_buttons,id'],
            'buttons.*.label' => ['required', 'string', 'max:100'],
            'buttons.*.label_ar' => ['nullable', 'string', 'max:100'],
            'buttons.*.url' => ['required', 'string', 'max:500', 'regex:/^(\/(?:[^\/\\\\].*)?|https?:\/\/[^\s]*)$/i'],
            'buttons.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'buttons.*.is_active' => ['nullable'],
            'buttons.*.open_in_new_tab' => ['nullable'],
        ]);

        $settings = LinkHubSetting::current();
        $settings->update([
            'headline' => $data['headline'],
            'headline_ar' => $data['headline_ar'] ?? null,
            'body' => $data['body'] ?? null,
            'body_ar' => $data['body_ar'] ?? null,
        ]);

        $posted = collect($data['buttons'] ?? []);
        $keepIds = [];

        foreach ($posted as $index => $row) {
            $payload = [
                'label' => $row['label'],
                'label_ar' => $row['label_ar'] ?? null,
                'url' => trim($row['url']),
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'is_active' => $this->checkboxValue($row['is_active'] ?? null),
                'open_in_new_tab' => $this->checkboxValue($row['open_in_new_tab'] ?? null, true),
            ];

            if (! empty($row['id'])) {
                $button = LinkHubButton::query()->find($row['id']);
                if ($button) {
                    $button->update($payload);
                    $keepIds[] = $button->id;

                    continue;
                }
            }

            $created = LinkHubButton::query()->create($payload);
            $keepIds[] = $created->id;
        }

        LinkHubButton::query()
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();

        Cache::forget('storefront.sitemap');

        return back()->with('success', 'Link page saved.');
    }

    protected function checkboxValue(mixed $value, bool $default = false): bool
    {
        if (is_array($value)) {
            $value = end($value);
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
