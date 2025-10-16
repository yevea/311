<?php
// Define the valid languages and their slugs
$language_slugs = [
    'en' => 'wood-worktop',  // Updated slug for English
    'de' => 'holzplatte',
    'fr' => 'plan-bois',
    'es' => 'madera',
];

// Get the requested URI
$request_uri = $_SERVER['REQUEST_URI'];
// Remove the base path /wood-worktop from the URI
$base_path = '/wood-worktop';
if (str_starts_with($request_uri, $base_path)) {
    $request_uri = substr($request_uri, strlen($base_path));
}

// Remove query string to get a clean path
$path = parse_url($request_uri, PHP_URL_PATH);

// Get the language code and slug from the URL
$segments = array_filter(explode('/', $path));

// Set a fallback to Spanish if no language is in the URL
$language_code = !empty($segments) ? strtolower(array_shift($segments)) : 'es';

// Set a fallback to Spanish if the language is not recognized
if (!array_key_exists($language_code, $language_slugs)) {
    $language_code = 'es';
}

// Ensure the URL matches the expected slug for the language
$expected_slug = $language_slugs[$language_code];
$current_slug = !empty($segments) ? array_shift($segments) : '';

// If the URL doesn't match the expected slug, redirect
if ($current_slug !== $expected_slug) {
    header('Location: /wood-worktop/' . $language_code . '/' . $expected_slug);
    exit;
}

// Path to the core PWA index.html
// Adjusted path to be relative to the new index.php location
$pwa_html_path = '../index.html';

// Check if the HTML file exists
if (!file_exists($pwa_html_path)) {
    http_response_code(404);
    echo "PWA not found.";
    exit;
}

// Load the index.html content
$html_content = file_get_contents($pwa_html_path);

// Inject JavaScript variables into the <head> section
$injected_js = "
<script>
    window.currentLanguage = '{$language_code}';
    window.currentSlug = '{$expected_slug}';
</script>";

// Find a suitable place to inject the script (just before </head>)
$html_content = str_replace('</head>', $injected_js . '</head>', $html_content);

// Serve the modified HTML
echo $html_content;
