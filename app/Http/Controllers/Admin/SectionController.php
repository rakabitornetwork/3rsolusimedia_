<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function edit(PageSection $section): Response
    {
        return Inertia::render('Admin/SectionEdit', [
            'section' => $section,
        ]);
    }

    public function update(Request $request, PageSection $section): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'content_json' => ['nullable', 'string'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'is_visible' => ['nullable'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:10240'],
            'image_secondary' => ['nullable', 'image', 'max:10240'],
            'remove_image' => ['nullable'],
            'remove_image_secondary' => ['nullable'],
        ]);

        $content = $section->content;
        if (! empty($validated['content_json'])) {
            $decoded = json_decode($validated['content_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['content_json' => 'JSON konten tidak valid.'])->withInput();
            }
            $content = $decoded;
        }

        $image = $section->image;
        $imageSecondary = $section->image_secondary;

        if ($request->boolean('remove_image') && $image) {
            $this->deletePublicImage($image);
            $image = null;
        }

        if ($request->boolean('remove_image_secondary') && $imageSecondary) {
            $this->deletePublicImage($imageSecondary);
            $imageSecondary = null;
        }

        if ($request->hasFile('image')) {
            if ($image) {
                $this->deletePublicImage($image);
            }
            $image = '/storage/'.$request->file('image')->store('uploads/sections', 'public');
        }

        if ($request->hasFile('image_secondary')) {
            if ($imageSecondary) {
                $this->deletePublicImage($imageSecondary);
            }
            $imageSecondary = '/storage/'.$request->file('image_secondary')->store('uploads/sections', 'public');
        }

        $section->update([
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'body' => $validated['body'] ?? null,
            'content' => $content,
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'is_visible' => $request->boolean('is_visible'),
            'sort_order' => $validated['sort_order'],
            'image' => $image,
            'image_secondary' => $imageSecondary,
        ]);

        return redirect()->route('admin.website.sections')->with('success', 'Section berhasil diperbarui.');
    }

    private function deletePublicImage(string $path): void
    {
        if (str_starts_with($path, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $path));
        }
    }
}
