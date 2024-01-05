{{-- answer-report.blade.php --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Answer Report - {{ $competition->name }}</title>
    <!-- Include Bootstrap CSS for styling (optional) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- In <head> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/multi-select/0.9.12/css/multi-select.min.css">

    <!-- Before </body> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/multi-select/0.9.12/js/jquery.multi-select.min.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1>Answer Report - {{ $competition->name }}</h1>

        <form action="{{ route('test.answer-report.post', ['competition' => $competition->id]) }}" method="POST">
            @csrf  {{-- CSRF token for form submission --}}

            {{-- Select box for Grade --}}
            <div class="form-group">
                <label for="gradeSelect">Select Grade</label>
                <select class="form-control" id="gradeSelect" name="grade">
                    @foreach($grades as $grade)
                        <option value="{{ $grade['id'] }}">{{ $grade['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Select box for Countries (multiple) -->
            <div class="form-group">
                <label for="countrySelect">Select Countries</label>
                <select multiple="multiple" class="form-control" id="countrySelect" name="countries[]">
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>


            {{-- Submit Button --}}
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <!-- Include Bootstrap JS (optional) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#countrySelect').multiSelect();
        });
    </script>    
</body>
</html>
