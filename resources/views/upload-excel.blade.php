<!DOCTYPE html>
<html>
<head>
    <title>Excel File Upload</title>
</head>
<body>
    <h1>Excel File Upload</h1>
    <form method="post" action="{{ route('uploadExcel') }}" enctype="multipart/form-data">
        {{ csrf_field() }}
        <input type="file" name="excel_file">
        <button type="submit">Upload</button>
    </form>
</body>
</html>