<!DOCTYPE html>
<html>
<head>
    <title>Upload Results Only</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

</head>
<body>
    @if(session()->has('message'))
        <div class="alert alert-success text-center">
            {{ session()->get('message') }}
        </div>
    @endif
    <h2>Upload Results Only</h2>
    <form method="post" action="{{ route('upload-results-csv') }}" enctype="multipart/form-data">
        {{ csrf_field() }}
        <input type="file" name="excel_file">
        <button class="btn btn-outline-primary" type="submit">Upload</button>
    </form>
</body>
</html>