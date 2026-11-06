<h1>Products</h1>

@forelse ($products as $product)
    <h3>{{ $product->name }}</h3>
    <p>₱{{ $product->price }}</p>
@empty
    <p>No products yet.</p>
@endforelse
