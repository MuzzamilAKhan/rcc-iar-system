<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Product Details | RCC-IAR</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 p-6">

    <main class="max-w-7xl mx-auto">

        <a href="{{ route('admin.products') }}" class="text-blue-700">
            &larr; Back to Products
        </a>

        <div class="mt-6 mb-8">
            <h1 class="text-3xl font-bold">
                {{ $product->product_name }}
            </h1>

            <p class="text-slate-500 mt-2">
                SAP Code: {{ $product->sap_code }}
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">

            <h2 class="text-xl font-bold mb-5">
                Product Information
            </h2>

            <div class="grid md:grid-cols-3 gap-6">

                <div>
                    <p class="text-slate-500">Brand</p>
                    <p class="font-semibold">
                        {{ $product->brand ?? 'N/A' }}
                    </p>
                </div>

                <div>
                    <p class="text-slate-500">ICC Code</p>
                    <p class="font-semibold">
                        {{ $product->icc_code ?? 'N/A' }}
                    </p>
                </div>

                <div>
                    <p class="text-slate-500">Business Unit</p>
                    <p class="font-semibold">
                        {{ $product->businessUnit?->name ?? 'N/A' }}
                    </p>
                </div>

                <div>
                    <p class="text-slate-500">Commercial Status</p>
                    <p class="font-semibold">
                        {{ $product->global_commercial_status ?? 'N/A' }}
                    </p>
                </div>

                <div>
                    <p class="text-slate-500">Basic UDI</p>
                    <p class="font-semibold">
                        {{ $product->basic_udi ?? 'N/A' }}
                    </p>
                </div>

                <div>
                    <p class="text-slate-500">GMDN Code</p>
                    <p class="font-semibold">
                        {{ $product->gmdn_code ?? 'N/A' }}
                    </p>
                </div>

            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">

            <h2 class="text-xl font-bold mb-5">
                Country Registrations
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left">

                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-3">Country</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Certificate</th>
                            <th class="p-3">Issue Date</th>
                            <th class="p-3">Expiry Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($product->registrations as $registration)

                            <tr class="border-b">
                                <td class="p-3">
                                    {{ $registration->country?->name }}
                                </td>

                                <td class="p-3">
                                    {{ ucfirst($registration->registration_status) }}
                                </td>

                                <td class="p-3">
                                    {{ $registration->registration_number ?? 'N/A' }}
                                </td>

                                <td class="p-3">
                                    {{ $registration->certificate_issue_date ?? 'N/A' }}
                                </td>

                                <td class="p-3">
                                    {{ $registration->certificate_expiry_date ?? 'N/A' }}
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">
                                    No country registrations found
                                    for this product.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>

    </main>
</body>

</html>
