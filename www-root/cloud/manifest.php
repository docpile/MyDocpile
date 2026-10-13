<?php
// Define the output as JSON so the browser parses it correctly
header('Content-Type: application/json; charset=utf-8');

// Fetch and format the domain name
$host = $_SERVER['HTTP_HOST'];
$parts = explode('.', $host);
if (count($parts) > 1) {
     array_pop($parts); // Remove the TLD
}
$domain = ucfirst(strtolower(implode('.', $parts)));

$manifest = [
    "name" => $domain,  
    "short_name" => $domain,
    "description" => "Secure email, file management and cloud storage.",
    "id" => strtolower($domain) . "_cloud_app",
    "start_url" => "/cloud/index.php",
    "scope" => "/cloud/",
    "display" => "standalone",
    "background_color" => "#f0f0f0",
    "theme_color" => "#000000",
    "orientation" => "any",
    "icons" => [
        [
            "src" => "/cloud/images/cloud-logo-512-square.png",
            "sizes" => "130x130",
            "type" => "image/png"
        ],
        [
            "src" => "/cloud/images/cloud-logo-512-square.png",
            "sizes" => "192x192",
            "type" => "image/png"
        ],
        [
            "src" => "/cloud/images/cloud-logo-512-square.png",
            "sizes" => "512x512",
            "type" => "image/png"
        ],
        [
            "src" => "/cloud/images/cloud-logo-512-square.png",
            "sizes" => "1290x1286",
            "type" => "image/png"
        ]
    ],
    "share_target" => [
        "action" => "/cloud/?shared_from_os=1",
        "method" => "POST",
        "enctype" => "multipart/form-data",
        "params" => [
            "title" => "title",
            "text" => "text",
            "url" => "url",
            "files" => [
                [
                    "name" => "shared_files[]",
                    "accept" => ["*/*"]
                ]
            ]
        ]
    ]
];

// Output the array as a formatted JSON string
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>