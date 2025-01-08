<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

return new class extends Migration
{
    public function up()
    {
        $this->updateParticipantsGrade('Grade Attempted - Correct Grade');
    }

    public function down()
    {
        $this->updateParticipantsGrade('Grade Registered in CMS');
    }

    protected function updateParticipantsGrade($gradeColumnName)
    {
        $filePath = public_path('2024-AMO-SIU-Correct-Grade-to-update-in-CMS-1.xlsx');

        if (!file_exists($filePath)) {
            throw new \Exception("Excel file not found at $filePath");
        }

        // Load the Excel data into an array
        $data = Excel::toArray([], $filePath);
        $sheet = $data[0];

        // Extract headers
        $headers = array_map('trim', $sheet[0]); // First row is header
        unset($sheet[0]); // Remove header row from data
        
        foreach ($sheet as $row) {
            // Map row to headers
            $row = array_combine($headers, $row);

            $indexNo = $row['index_no'] ?? null;
            $newGrade = $row[$gradeColumnName] ?? null;

            if ($indexNo && $newGrade) {
                DB::table('participants')
                    ->where('index_no', $indexNo)
                    ->update(['grade' => $newGrade]);
            }
        }
    }
};
