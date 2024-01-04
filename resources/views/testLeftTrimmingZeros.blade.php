{{-- testLeftTrimmingZeroes.blade.php --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Left Trimming Zeroes</title>
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .editable {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Test Left Side Trimming Zeros</h1>
        <div class="info">
            <p>Competition: 2023 SMC</p>
            <p>Country: Philippines</p>
            <p>Grade: G1</p>
            <p>Question: Q1</p>
            <p>Task ID: 3062</p>
            <p>Answer Key: <input type="text" id="taskAnswer" value="{{ $taskAnswer->answer }}" class="editable"></p>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Participant Index</th>
                    <th>Participant Answer</th>
                    <th>Is Correct</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                @foreach($answers as $answer)
                    <tr>
                        <td>{{ $answer->participant_index }}</td>
                        <td><input type="text" class="editable" data-id="{{ $answer->id }}" value="{{ $answer->answer }}"></td>
                        <td>{{ $answer->task_id ? 'Yes' : 'No' }}</td>
                        <td>{{ $answer->is_correct ? 'Yes' : 'No' }}</td>
                        <td>{{ $answer->score }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button id="saveButton" class="btn btn-primary">Save</button>
    </div>

    <!-- Include jQuery and Bootstrap JS -->
    <!-- Replace the jQuery slim version with the full version -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#saveButton').click(function() {
                let taskAnswer = $('#taskAnswer').val();
                let answers = [];
                $('.editable').each(function() {
                    let id = $(this).data('id');
                    let value = $(this).val();
                    if (id) {
                        answers.push({ id: id, answer: value });
                    }
                });

                // AJAX request to update the data
                $.ajax({
                    url: '/test/temporarly-link', // Your route to update the answers
                    method: 'POST',
                    data: {
                        taskAnswer: taskAnswer,
                        answers: answers,
                        _token: '{{ csrf_token() }}' // CSRF token
                    },
                    success: function(response) {
                        alert('Data updated successfully');
                    },
                    error: function() {
                        alert('An error occurred');
                    }
                });
            });
        });
    </script>
</body>
</html>
