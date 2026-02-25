<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @if(!empty($customCss))
    <style>
        {!! $customCss !!}
    </style>
    @endif
</head>
<body>
    <script
        id="api-reference"
        data-url="{{ $specUrl }}"
        data-configuration="{{ json_encode($configuration) }}"
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
