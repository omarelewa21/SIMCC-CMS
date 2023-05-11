<!DOCTYPE html>

<html>
    <head>
        <link rel="stylesheet" href={{ asset('css/app.css') }}>
    </head>

    <body>
      <div class="root">
         <header>
            <h1> {{ $general_data['competition'] }} </h1>
            <h2> Individual student Report </h2>
         </header>
         <hr>
   
         <div class="container">
            <div class="general-info">
               <div class="name">
                  <p> {{ $general_data['particiapnt'] }} </p>
               </div>
               <div class="grade-school">
                  <div class="vertical">
                     <div>
                        <span class="title">Grade</span>
                        <span class="data"> {{ $general_data['grade'] }} </span>
                     </div>
                     <div style="margin-top: 10px">
                        <span class="title">Type Of Candidate</span>
                        <span class="data">{{$general_data['is_private'] ? 'Private' : 'School'}} Candidate</span>
                     </div>
                  </div>
                  <div class="horizontal" style="float: right">
                     <span class="title">School</span>
                     <span class="data"> {{ $general_data['school'] }} </span>
                  </div>
               </div>
            </div>

            <div class="section performance-by-questions">
               <span style="font-size:x-large; font-weight: bold; display:block; margin-bottom:8px;">Performance by Questions</span>
               <span style="color:grey">Table below shows which questions Student got correct.</span>
               <div class="data">
                  @foreach ($performance_by_questions as $question=>$is_correct)
                  <div>
                     <span> {{ $question }} </span>
                     <span> {{ $is_correct ? 'Correct' : 'Wrong' }} </span>
                     <span>
                        @if ($is_correct)
                           @php
                              $svg = base64_encode('<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(20, 74, 148); font-size: 20px;">
                                 <path stroke="blue" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path>
                                 <path stroke="blue" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path>
                              </svg>')
                           @endphp
                           <img src="data:image/svg+xml;base64,{!! $svg !!}" height="10" width="10" style="float: right;"/>
                        @else
                           @php
                              $svg = base64_encode('<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: red; font-size: 20px;">
                                 <path fill="none" stroke-miterlimit="10" stroke-width="32" stroke="red" d="M448 256c0-106-86-192-192-192S64 150 64 256s86 192 192 192 192-86 192-192z"></path>
                                 <path fill="none" stroke-linecap="round" stroke-linejoin="round" stroke="red" stroke-width="32" d="M320 320L192 192m0 128l128-128"></path>
                              </svg>')
                           @endphp
                           <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.5" width="0.5" style="float: right;"/>
                        @endif
                     </span>
                  </div>
                  @endforeach
               </div>
            </div>

            <div class="section performance-by-topics">
               <span style="font-size:x-large; font-weight: bold; display:block; margin-bottom:8px;">Performance by Topics</span>
               <span style="color:grey">Cards below shows student score in each topic compared to all (his/her school's) students and (his/her country's) students.</span>
               <div class="data">
                  @foreach ($performance_by_topics as $array)
                     <div class="card">
                        <p class="topic"> {{ $array['topic'] }} </p>
                        <div style="width:90%">
                           <span style="font-weight: bold"> {{ $array['participant'] }} % </span>
                           @php
                              $svg = base64_encode('<svg stroke="currentColor" fill="white" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(255, 255, 255); font-size: 30px;">
                                 <path d="M256 256c52.805 0 96-43.201 96-96s-43.195-96-96-96-96 43.201-96 96 43.195 96 96 96zm0 48c-63.598 0-192 32.402-192 96v48h384v-48c0-63.598-128.402-96-192-96z"></path>
                              </svg>')
                           @endphp
                           <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.6" width="0.6" style="float: right;"/>
                        </div>
                        <hr width="100%">
                        <div style="width:90%">
                           <span> {{ $array['school'] }} % </span>
                           @php
                              $svg = base64_encode('<svg stroke="currentColor" fill="white" stroke-width="0" viewBox="0 0 640 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(255, 255, 255); font-size: 30px;">
                                 <path d="M622.34 153.2L343.4 67.5c-15.2-4.67-31.6-4.67-46.79 0L17.66 153.2c-23.54 7.23-23.54 38.36 0 45.59l48.63 14.94c-10.67 13.19-17.23 29.28-17.88 46.9C38.78 266.15 32 276.11 32 288c0 10.78 5.68 19.85 13.86 25.65L20.33 428.53C18.11 438.52 25.71 448 35.94 448h56.11c10.24 0 17.84-9.48 15.62-19.47L82.14 313.65C90.32 307.85 96 298.78 96 288c0-11.57-6.47-21.25-15.66-26.87.76-15.02 8.44-28.3 20.69-36.72L296.6 284.5c9.06 2.78 26.44 6.25 46.79 0l278.95-85.7c23.55-7.24 23.55-38.36 0-45.6zM352.79 315.09c-28.53 8.76-52.84 3.92-65.59 0l-145.02-44.55L128 384c0 35.35 85.96 64 192 64s192-28.65 192-64l-14.18-113.47-145.03 44.56z"></path>
                              </svg>')
                           @endphp
                           <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.5" width="0.5" style="float: right;"/>
                        </div>
                        <hr width="100%">
                        <div style="width:90%">
                           <span> {{ $array['country'] }} % </span>
                           @php
                              $svg = base64_encode('<svg stroke="currentColor" fill="white" stroke-width="0" viewBox="0 0 1024 1024" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(255, 255, 255); font-size: 30px;">
                                 <path d="M880 305H624V192c0-17.7-14.3-32-32-32H184v-40c0-4.4-3.6-8-8-8h-56c-4.4 0-8 3.6-8 8v784c0 4.4 3.6 8 8 8h56c4.4 0 8-3.6 8-8V640h248v113c0 17.7 14.3 32 32 32h416c17.7 0 32-14.3 32-32V337c0-17.7-14.3-32-32-32z"></path>
                              </svg>')
                           @endphp
                           <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.25" width="0.25" style="float: right;"/>
                        </div>
                     </div>
                  @endforeach
               </div>
               <div class="appendix" style="margin-top: 0">
                  <div>
                     <span class="appendix-span-title">
                        @php
                           $svg = base64_encode('<svg stroke="currentColor" fill="rgb(20, 74, 148)" stroke-width="0" viewBox="0 0 512 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(255, 255, 255); font-size: 30px;">
                                 <path d="M256 256c52.805 0 96-43.201 96-96s-43.195-96-96-96-96 43.201-96 96 43.195 96 96 96zm0 48c-63.598 0-192 32.402-192 96v48h384v-48c0-63.598-128.402-96-192-96z"></path>
                              </svg>')
                        @endphp
                        <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.5" width="0.5" style="float: left;"/>
                     </span>
                     <span style="margin-left:20px"> 
                        Percentage of the questions which current student scored correct for this topic.
                     </span>
                  </div>
                  <div>
                     <span class="appendix-span-title">
                        @php
                           $svg = base64_encode('<svg stroke="currentColor" fill="rgb(20, 74, 148)" stroke-width="0" viewBox="0 0 640 512" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(255, 255, 255); font-size: 30px;">
                              <path d="M622.34 153.2L343.4 67.5c-15.2-4.67-31.6-4.67-46.79 0L17.66 153.2c-23.54 7.23-23.54 38.36 0 45.59l48.63 14.94c-10.67 13.19-17.23 29.28-17.88 46.9C38.78 266.15 32 276.11 32 288c0 10.78 5.68 19.85 13.86 25.65L20.33 428.53C18.11 438.52 25.71 448 35.94 448h56.11c10.24 0 17.84-9.48 15.62-19.47L82.14 313.65C90.32 307.85 96 298.78 96 288c0-11.57-6.47-21.25-15.66-26.87.76-15.02 8.44-28.3 20.69-36.72L296.6 284.5c9.06 2.78 26.44 6.25 46.79 0l278.95-85.7c23.55-7.24 23.55-38.36 0-45.6zM352.79 315.09c-28.53 8.76-52.84 3.92-65.59 0l-145.02-44.55L128 384c0 35.35 85.96 64 192 64s192-28.65 192-64l-14.18-113.47-145.03 44.56z"></path>
                           </svg>')
                        @endphp
                        <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.5" width="0.5" style="float: left;"/>
                     </span>
                     <span style="margin-left:20px"> 
                        Percentage of the questions which current student's school scored correct for this topic.
                     </span>
                  </div>
                  <div>
                     <span class="appendix-span-title">
                        @php
                           $svg = base64_encode('<svg stroke="currentColor" fill="rgb(20, 74, 148)" stroke-width="0" viewBox="0 0 1024 1024" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg" style="color: rgb(255, 255, 255); font-size: 30px;">
                              <path d="M880 305H624V192c0-17.7-14.3-32-32-32H184v-40c0-4.4-3.6-8-8-8h-56c-4.4 0-8 3.6-8 8v784c0 4.4 3.6 8 8 8h56c4.4 0 8-3.6 8-8V640h248v113c0 17.7 14.3 32 32 32h416c17.7 0 32-14.3 32-32V337c0-17.7-14.3-32-32-32z"></path>
                           </svg>')
                        @endphp
                        <img src="data:image/svg+xml;base64,{!! $svg !!}" height="0.25" width="0.25" style="float: right;"/>
                     </span>
                     <span style="margin-left:20px"> 
                        Percentage of the questions which current student's country scored correct for this topic.
                     </span>
                  </div>
               </div>
            </div>

            <div class="section">
               <span style="font-size:x-large; font-weight: bold; display:block; margin-bottom:8px;">Grade Performance Analysis</span>
               <span style="color:grey">Table below shows student score in each topic compared to all his/her school's students.</span>
               <table class="datatable">
                  <tr>
                     <th>Domain</th>
                     <th>Topic</th>
                     <th>Your Score</th>
                     <th>School Range</th>
                     <th>Average</th>
                  </tr>
                  @foreach ($grade_performance_analysis as $array)
                     <tr>
                        <td>{{ $array['domain'] }}</td>
                        <td>{{ $array['topic'] }}</td>
                        <td>{{ $array['participant_score'] }}</td>
                        <td>{{ $array['school_range'] }}</td>
                        <td>{{ round($array['school_average'], 2) }}</td>
                     </tr>
                  @endforeach
               </table>
               <div class="appendix">
                  <div>
                     <span class="appendix-span-title">School Range :</span>
                        Lowest-Highest score achieved by students in this topic within student's school.
                  </div>
                  <div>
                     <span class="appendix-span-title">Average :</span>
                        Average score achieved by students in this topic within student's school.
                  </div>
               </div>
            </div>
   
            <div class="section">
               <p style="font-size:x-large; font-weight: bold; display:block; margin-bottom:8px;">Analysis By Question</p>
               <span style="color:grey">Table below shows student performance per question compared to all (his/her school's) students and (his/her country's) students..</span>
               <table class="datatable">
                  <tr>
                     <th>Question</th>
                     <th>Topic</th>
                     <th>Answer Correct</th>
                     <th>% Correct in Your School</th>
                     <th>% Correct in Your Country</th>
                     <th>Level of Difficulty</th>
                  </tr>
   
                  @foreach ($analysis_by_questions as $array)
                     <tr>
                        <td>{{ $array['question'] }}</td>
                        <td>{{ $array['topic'] }}</td>
                        <td>{{ $array['is_correct'] ? 'Yes' : 'No'}}</td>
                        <td>{{ $array['correct_in_school'] }}</td>
                        <td>{{ $array['correct_in_country'] }}</td>
                        <td>{{ $array['diffculty'] }}</td>
                     </tr>
                  @endforeach
               </table>
               <div class="appendix">
                  <div>
                     <span class="appendix-span-title">Answer Correct :</span>
                     Whether the student answered the question correctly or not.
                  </div>
                  <div>
                     <span class="appendix-span-title">% Correct in Your School :</span>
                     Pecentage of students in the school who answered the question correctly.
                  </div>
                  <div>
                     <span class="appendix-span-title">% Correct in Your Country :</span>
                     Percentage of students in the country who answered the question correctly.
                  </div>
               </div>
            </div>
         </div>
      </div>
    </body>
</html>