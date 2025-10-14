<?php
// Set the content type to JSON to ensure the browser and JavaScript parse it correctly
header('Content-Type: application/json');

// Retrieve the requested language code from the URL parameter.
// Default to 'es' if no language is specified.
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'es';

// Define the path to the configuration files directory, which is outside of public_html
$config_path = '../wood-config/';

// Get the path for the requested language file
$language_file = $config_path . $lang . '.json';

// Fallback to Spanish if the requested language file does not exist
if (!file_exists($language_file)) {
    $lang = 'es';
    $language_file = $config_path . $lang . '.json';
}

// Check again if the Spanish fallback file exists. If not, something is wrong.
if (!file_exists($language_file)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Language configuration file not found.']);
    exit;
}

// Load the translations for the selected language
$translations = json_decode(file_get_contents($language_file), true);

// Load the main product data configuration
$product_data_file = $config_path . 'product-data.json';
if (!file_exists($product_data_file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Product data configuration file not found.']);
    exit;
}
$product_data = json_decode(file_get_contents($product_data_file), true);

// Combine the data into a single array
$response_data = [
    'translations' => $translations,
    'product_data' => $product_data,
];

// Return the combined data as a JSON object
echo json_encode($response_data);

?>
