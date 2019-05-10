<html>
<head>
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="https://cdnjs.cloudflare.com/ajax/libs/babel-polyfill/6.26.0/polyfill.min.js"></script>
</head>
<body>
<div id="app">
<vue-word-cloud
    :words="[['romance', 19], ['horror', 3], ['fantasy', 7], ['adventure', 3]]"
    :color="(function (_ref) {  var weight = _ref[1];  return weight > 10 ? 'DeepPink' : weight > 5 ? 'RoyalBlue' : 'Indigo';})"
    font-family="Roboto"
></vue-word-cloud>
</div>
</body>
<script src="https://unpkg.com/vue" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/vuewordcloud" crossorigin="anonymous"></script>
    <script src="https://nullterminated.org/js/app.js"></script>
    <script defer src="https://use.fontawesome.com/releases/v5.0.13/js/all.js" integrity="sha384-xymdQtn1n3lH2wcu0qhcdaOpQwyoarkgLVxC/wZ5q7h9gHtxICrpcaSUfygqZGOe" crossorigin="anonymous"></script>
</html>