<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('category.index', [
            'categories' => Category::orderBy('type')->orderBy('name')->get()->groupBy('type'),
        ]);
    }

    public function create()
    {
        return view('category.form', ['category' => null]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));
        return redirect()->route('category')->with('success', 'Kategori berhasil ditambahkan!');
    }

    public function edit(string $id)
    {
        return view('category.form', ['category' => Category::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);
        $category->update($this->validated($request, $category->id));
        return redirect()->route('category')->with('success', 'Kategori berhasil diubah');
    }

    public function destroy(string $id)
    {
        Category::findOrFail($id)->delete(); // transaksi terkait menjadi "tanpa kategori"
        return redirect()->route('category')->with('success', 'Kategori berhasil dihapus!');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'type'         => ['required', Rule::in(Transaction::TYPES)],
            'name'         => ['required', 'string', 'max:255',
                Rule::unique('categories')->where('type', $request->input('type'))->ignore($ignoreId)],
            'descriptions' => ['nullable', 'string'],
        ]);
    }
}
