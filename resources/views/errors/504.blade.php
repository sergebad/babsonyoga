<html>
<head>
    <title>
        Babson Yoga Error
    </title>
    <style>
        body {
            background-color: #f9f9f9;
            color: #333;
            text-align: center;
            text-shadow: 0 1px 0 #fff;
            font-size: 1.8em;
        }
        .missing {
            width: 250px;
            margin: 0 auto;
            margin-top: 50px;
            padding: 40px;
        }
    </style>
</head>
<body>
<div class="missing">
    <h2>Stay calm and carry on ... there was an error with your request</h2>
    {{ $exception->getMessage() }}
</div>
</body>
</html>
