<?php
// Set headers to allow cross-origin requests from your PWA
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For development, should be restricted in production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
    exit();
}

// Include the Stripe PHP library
require_once '../wood-stripe-php/init.php';

// Set your Stripe secret key
\Stripe\Stripe::setApiKey('YOUR_STRIPE_SECRET_KEY');

// Path to your configuration and data files
$config_path = '../wood-config/';
$orders_path = '../wood-data/orders/';

try {
    // Get the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate incoming data
    if (!isset($data['order_details']) || !isset($data['payment_method_id']) || !isset($data['language'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Missing data in request.']);
        exit();
    }

    $orderDetails = $data['order_details'];
    $paymentMethodId = $data['payment_method_id'];
    $language = $data['language'];

    // Load product data and translations for server-side validation
    $product_data = json_decode(file_get_contents($config_path . 'product-data.json'), true);
    $translations = json_decode(file_get_contents($config_path . $language . '.json'), true);

    // --- Server-Side Price Calculation ---
    // This prevents malicious users from manipulating the price on the frontend.
    $width_cm = (float) $orderDetails['width'];
    $depth_cm = (float) $orderDetails['depth'];
    $wood_type = $orderDetails['wood_type'];
    // ... add more validation for edges, usage etc.

    if ($width_cm <= 0 || $depth_cm <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid dimensions.']);
        exit();
    }

    // You can define a server-side price map in product-data.json
    // For this example, we use the price from the translations file.
    $price_per_sq_cm = $translations['price_per_sq_cm'];

    $total_amount_eur = ($width_cm * $depth_cm) * $price_per_sq_cm;
    $amount_in_cents = round($total_amount_eur * 100);

    // Create a PaymentIntent with Stripe
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $amount_in_cents,
        'currency' => 'eur',
        'payment_method' => $paymentMethodId,
        'confirmation_method' => 'manual',
        'confirm' => true,
        'description' => "Wood Countertop Order",
    ]);

    // Handle the payment intent status
    if ($paymentIntent->status == 'succeeded') {
        // Payment succeeded, save the order and respond to the frontend
        $order_id = uniqid('order_');
        $timestamp = date('c');

        $order_file_name = $orders_path . $order_id . '.json';
        $order_data = [
            'order_id' => $order_id,
            'timestamp' => $timestamp,
            'status' => 'completed',
            'payment_intent_id' => $paymentIntent->id,
            'order_details' => $orderDetails,
            'total_amount_eur' => $total_amount_eur,
        ];
        file_put_contents($order_file_name, json_encode($order_data, JSON_PRETTY_PRINT));

        echo json_encode(['success' => true, 'order_id' => $order_id]);

    } elseif ($paymentIntent->status == 'requires_action') {
        // Payment requires additional authentication
        echo json_encode([
            'requires_action' => true,
            'payment_intent_id' => $paymentIntent->id,
            'client_secret' => $paymentIntent->client_secret,
        ]);

    } else {
        // Other non-successful payment intent status
        http_response_code(400);
        echo json_encode(['error' => 'Payment failed.', 'status' => $paymentIntent->status]);
    }

} catch (\Stripe\Exception\CardException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected server error occurred.']);
}
?>
