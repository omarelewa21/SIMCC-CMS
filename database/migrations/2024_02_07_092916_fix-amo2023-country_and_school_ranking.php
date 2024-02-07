<?php

use App\Models\CompetitionParticipantsResults;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $data = [
        [
            "index" => "066231001522",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231000986",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001333",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001340",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001267",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001643",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001521",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001561",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001432",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001509",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001358",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001558",
            "country_rank" => 12,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001158",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001258",
            "country_rank" => 13,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001389",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001431",
            "country_rank" => 13,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001630",
            "country_rank" => 17,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001559",
            "country_rank" => 18,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001603",
            "country_rank" => 19,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001247",
            "country_rank" => 20,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001153",
            "country_rank" => 21,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001255",
            "country_rank" => 21,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001538",
            "country_rank" => 21,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001560",
            "country_rank" => 24,
            "school_rank" => 4,
        ],
        [
            "index" => "066231000983",
            "country_rank" => 25,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001263",
            "country_rank" => 25,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001632",
            "country_rank" => 27,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001764",
            "country_rank" => 27,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001765",
            "country_rank" => 27,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001766",
            "country_rank" => 27,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001577",
            "country_rank" => 31,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001262",
            "country_rank" => 32,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001388",
            "country_rank" => 33,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001631",
            "country_rank" => 33,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001636",
            "country_rank" => 33,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001644",
            "country_rank" => 33,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001266",
            "country_rank" => 37,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001396",
            "country_rank" => 38,
            "school_rank" => 1,
        ],
        [
            "index" => "066231000980",
            "country_rank" => 39,
            "school_rank" => 3,
        ],
        [
            "index" => "066231000988",
            "country_rank" => 39,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001252",
            "country_rank" => 41,
            "school_rank" => 9,
        ],
        [
            "index" => "066231000979",
            "country_rank" => 42,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001256",
            "country_rank" => 42,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001257",
            "country_rank" => 42,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001499",
            "country_rank" => 42,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001511",
            "country_rank" => 42,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001715",
            "country_rank" => 42,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001066",
            "country_rank" => 48,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001641",
            "country_rank" => 48,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001254",
            "country_rank" => 50,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001615",
            "country_rank" => 50,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001157",
            "country_rank" => 52,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001260",
            "country_rank" => 52,
            "school_rank" => 13,
        ],
        [
            "index" => "066231001382",
            "country_rank" => 52,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001637",
            "country_rank" => 52,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001693",
            "country_rank" => 52,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001726",
            "country_rank" => 52,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001068",
            "country_rank" => 58,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001067",
            "country_rank" => 59,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001334",
            "country_rank" => 59,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001562",
            "country_rank" => 59,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001633",
            "country_rank" => 59,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001120",
            "country_rank" => 63,
            "school_rank" => 1,
        ],
        [
            "index" => "066231000987",
            "country_rank" => 64,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001248",
            "country_rank" => 64,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001678",
            "country_rank" => 64,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001720",
            "country_rank" => 64,
            "school_rank" => 2,
        ],
        [
            "index" => "066231000982",
            "country_rank" => 68,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001069",
            "country_rank" => 68,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001121",
            "country_rank" => 68,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001159",
            "country_rank" => 68,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001716",
            "country_rank" => 68,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001719",
            "country_rank" => 68,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001362",
            "country_rank" => 74,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001714",
            "country_rank" => 74,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001721",
            "country_rank" => 74,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001156",
            "country_rank" => 77,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001261",
            "country_rank" => 77,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001677",
            "country_rank" => 77,
            "school_rank" => 2,
        ],
        [
            "index" => "066231000977",
            "country_rank" => 80,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001065",
            "country_rank" => 80,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001576",
            "country_rank" => 80,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001635",
            "country_rank" => 80,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001727",
            "country_rank" => 80,
            "school_rank" => 2,
        ],
        [
            "index" => "066231000981",
            "country_rank" => 85,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001119",
            "country_rank" => 85,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001155",
            "country_rank" => 85,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001196",
            "country_rank" => 85,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001639",
            "country_rank" => 85,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001728",
            "country_rank" => 85,
            "school_rank" => 3,
        ],
        [
            "index" => "066231000985",
            "country_rank" => 91,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001064",
            "country_rank" => 91,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001250",
            "country_rank" => 91,
            "school_rank" => 16,
        ],
        [
            "index" => "066231001259",
            "country_rank" => 91,
            "school_rank" => 16,
        ],
        [
            "index" => "066231001717",
            "country_rank" => 91,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001718",
            "country_rank" => 91,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001117",
            "country_rank" => 97,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001118",
            "country_rank" => 97,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001253",
            "country_rank" => 97,
            "school_rank" => 18,
        ],
        [
            "index" => "066231001264",
            "country_rank" => 97,
            "school_rank" => 18,
        ],
        [
            "index" => "066231001365",
            "country_rank" => 97,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001638",
            "country_rank" => 97,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001642",
            "country_rank" => 97,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001713",
            "country_rank" => 97,
            "school_rank" => 9,
        ],
        [
            "index" => "066231000976",
            "country_rank" => 105,
            "school_rank" => 11,
        ],
        [
            "index" => "066231000984",
            "country_rank" => 105,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001251",
            "country_rank" => 105,
            "school_rank" => 20,
        ],
        [
            "index" => "066231000975",
            "country_rank" => 108,
            "school_rank" => 13,
        ],
        [
            "index" => "066231001249",
            "country_rank" => 108,
            "school_rank" => 21,
        ],
        [
            "index" => "066231001265",
            "country_rank" => 108,
            "school_rank" => 21,
        ],
        [
            "index" => "066231001554",
            "country_rank" => 108,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001712",
            "country_rank" => 108,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001640",
            "country_rank" => 113,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001541",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001542",
            "country_rank" => 2,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001545",
            "country_rank" => 3,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001407",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001613",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001564",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001731",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001553",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001390",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001774",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001085",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001344",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001437",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001767",
            "country_rank" => 14,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001768",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001649",
            "country_rank" => 16,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001160",
            "country_rank" => 17,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001540",
            "country_rank" => 17,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001280",
            "country_rank" => 19,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001433",
            "country_rank" => 19,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001166",
            "country_rank" => 21,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001434",
            "country_rank" => 21,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001345",
            "country_rank" => 23,
            "school_rank" => 1,
        ],
        [
            "index" => "066231000992",
            "country_rank" => 24,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001271",
            "country_rank" => 24,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001574",
            "country_rank" => 24,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001269",
            "country_rank" => 27,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001276",
            "country_rank" => 28,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001694",
            "country_rank" => 29,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001086",
            "country_rank" => 30,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001164",
            "country_rank" => 30,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001605",
            "country_rank" => 30,
            "school_rank" => 2,
        ],
        [
            "index" => "066231000989",
            "country_rank" => 33,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001105",
            "country_rank" => 34,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001123",
            "country_rank" => 34,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001563",
            "country_rank" => 34,
            "school_rank" => 2,
        ],
        [
            "index" => "066231000996",
            "country_rank" => 37,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001001",
            "country_rank" => 37,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001006",
            "country_rank" => 37,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001281",
            "country_rank" => 37,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001004",
            "country_rank" => 41,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001163",
            "country_rank" => 42,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001167",
            "country_rank" => 42,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001278",
            "country_rank" => 42,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001408",
            "country_rank" => 42,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001539",
            "country_rank" => 42,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001084",
            "country_rank" => 47,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001197",
            "country_rank" => 47,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001604",
            "country_rank" => 47,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001732",
            "country_rank" => 47,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001087",
            "country_rank" => 51,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001130",
            "country_rank" => 51,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001645",
            "country_rank" => 51,
            "school_rank" => 2,
        ],
        [
            "index" => "066231000991",
            "country_rank" => 54,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001336",
            "country_rank" => 54,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001374",
            "country_rank" => 54,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001730",
            "country_rank" => 54,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001000",
            "country_rank" => 58,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001071",
            "country_rank" => 59,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001162",
            "country_rank" => 59,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001072",
            "country_rank" => 61,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001165",
            "country_rank" => 61,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001200",
            "country_rank" => 61,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001272",
            "country_rank" => 61,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001513",
            "country_rank" => 61,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001647",
            "country_rank" => 61,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001681",
            "country_rank" => 61,
            "school_rank" => 1,
        ],
        [
            "index" => "066231000993",
            "country_rank" => 68,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001074",
            "country_rank" => 68,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001088",
            "country_rank" => 68,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001161",
            "country_rank" => 68,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001544",
            "country_rank" => 68,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001648",
            "country_rank" => 68,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001651",
            "country_rank" => 68,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001652",
            "country_rank" => 68,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001653",
            "country_rank" => 68,
            "school_rank" => 4,
        ],
        [
            "index" => "066231000994",
            "country_rank" => 77,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001002",
            "country_rank" => 77,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001109",
            "country_rank" => 77,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001279",
            "country_rank" => 77,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001359",
            "country_rank" => 77,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001567",
            "country_rank" => 77,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001729",
            "country_rank" => 77,
            "school_rank" => 4,
        ],
        [
            "index" => "066231000999",
            "country_rank" => 84,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001007",
            "country_rank" => 84,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001089",
            "country_rank" => 84,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001199",
            "country_rank" => 84,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001335",
            "country_rank" => 84,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001368",
            "country_rank" => 84,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001075",
            "country_rank" => 90,
            "school_rank" => 4,
        ],
        [
            "index" => "066231000990",
            "country_rank" => 91,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001003",
            "country_rank" => 91,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001005",
            "country_rank" => 91,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001122",
            "country_rank" => 91,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001270",
            "country_rank" => 91,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001274",
            "country_rank" => 91,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001533",
            "country_rank" => 91,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001682",
            "country_rank" => 91,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001277",
            "country_rank" => 99,
            "school_rank" => 11,
        ],
        [
            "index" => "066231000995",
            "country_rank" => 100,
            "school_rank" => 17,
        ],
        [
            "index" => "066231001101",
            "country_rank" => 100,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001646",
            "country_rank" => 100,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001650",
            "country_rank" => 100,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001679",
            "country_rank" => 100,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001680",
            "country_rank" => 100,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001683",
            "country_rank" => 100,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001275",
            "country_rank" => 107,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001090",
            "country_rank" => 108,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001273",
            "country_rank" => 108,
            "school_rank" => 13,
        ],
        [
            "index" => "066231001436",
            "country_rank" => 108,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001526",
            "country_rank" => 108,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001268",
            "country_rank" => 112,
            "school_rank" => 14,
        ],
        [
            "index" => "066231000997",
            "country_rank" => 113,
            "school_rank" => 18,
        ],
        [
            "index" => "066231001070",
            "country_rank" => 114,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001523",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001398",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001337",
            "country_rank" => 3,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001410",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001416",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001342",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001078",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001373",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001201",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001292",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001020",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001131",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001547",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001108",
            "country_rank" => 14,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001128",
            "country_rank" => 15,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001392",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001609",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001341",
            "country_rank" => 18,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001546",
            "country_rank" => 19,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001512",
            "country_rank" => 20,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001658",
            "country_rank" => 21,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001372",
            "country_rank" => 22,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001440",
            "country_rank" => 22,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001351",
            "country_rank" => 24,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001288",
            "country_rank" => 25,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001290",
            "country_rank" => 25,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001734",
            "country_rank" => 25,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001696",
            "country_rank" => 28,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001283",
            "country_rank" => 29,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001587",
            "country_rank" => 29,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001438",
            "country_rank" => 31,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001770",
            "country_rank" => 31,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001409",
            "country_rank" => 33,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001366",
            "country_rank" => 34,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001289",
            "country_rank" => 35,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001568",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001771",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001293",
            "country_rank" => 38,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001442",
            "country_rank" => 38,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001491",
            "country_rank" => 38,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001008",
            "country_rank" => 41,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001565",
            "country_rank" => 42,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001772",
            "country_rank" => 43,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001127",
            "country_rank" => 44,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001296",
            "country_rank" => 44,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001532",
            "country_rank" => 44,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001735",
            "country_rank" => 44,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001129",
            "country_rank" => 48,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001295",
            "country_rank" => 49,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001654",
            "country_rank" => 50,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001202",
            "country_rank" => 51,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001284",
            "country_rank" => 51,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001439",
            "country_rank" => 53,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001629",
            "country_rank" => 53,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001370",
            "country_rank" => 55,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001023",
            "country_rank" => 56,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001586",
            "country_rank" => 56,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001627",
            "country_rank" => 56,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001736",
            "country_rank" => 56,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001009",
            "country_rank" => 60,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001690",
            "country_rank" => 60,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001022",
            "country_rank" => 62,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001091",
            "country_rank" => 62,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001021",
            "country_rank" => 64,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001079",
            "country_rank" => 65,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001624",
            "country_rank" => 65,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001608",
            "country_rank" => 67,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001722",
            "country_rank" => 67,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001733",
            "country_rank" => 67,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001011",
            "country_rank" => 70,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001298",
            "country_rank" => 70,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001411",
            "country_rank" => 70,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001444",
            "country_rank" => 70,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001285",
            "country_rank" => 74,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001391",
            "country_rank" => 74,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001399",
            "country_rank" => 74,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001443",
            "country_rank" => 74,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001684",
            "country_rank" => 74,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001691",
            "country_rank" => 74,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001017",
            "country_rank" => 80,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001525",
            "country_rank" => 80,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001534",
            "country_rank" => 80,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001692",
            "country_rank" => 80,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001724",
            "country_rank" => 80,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001445",
            "country_rank" => 85,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001013",
            "country_rank" => 86,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001168",
            "country_rank" => 86,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001583",
            "country_rank" => 86,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001695",
            "country_rank" => 86,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001019",
            "country_rank" => 90,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001294",
            "country_rank" => 90,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001297",
            "country_rank" => 90,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001655",
            "country_rank" => 90,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001076",
            "country_rank" => 94,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001010",
            "country_rank" => 95,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001014",
            "country_rank" => 95,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001018",
            "country_rank" => 95,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001623",
            "country_rank" => 95,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001656",
            "country_rank" => 95,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001723",
            "country_rank" => 95,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001016",
            "country_rank" => 101,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001125",
            "country_rank" => 101,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001762",
            "country_rank" => 101,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001012",
            "country_rank" => 104,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001015",
            "country_rank" => 104,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001287",
            "country_rank" => 104,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001291",
            "country_rank" => 104,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001524",
            "country_rank" => 104,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001092",
            "country_rank" => 109,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001441",
            "country_rank" => 109,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001622",
            "country_rank" => 109,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001657",
            "country_rank" => 112,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001685",
            "country_rank" => 112,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001282",
            "country_rank" => 114,
            "school_rank" => 16,
        ],
        [
            "index" => "066231001598",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001395",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001514",
            "country_rank" => 3,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001352",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001588",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001394",
            "country_rank" => 6,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001412",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001226",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001448",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001662",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001205",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001517",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001347",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001363",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001519",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001338",
            "country_rank" => 16,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001343",
            "country_rank" => 17,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001529",
            "country_rank" => 18,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001589",
            "country_rank" => 18,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001308",
            "country_rank" => 20,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001626",
            "country_rank" => 20,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001209",
            "country_rank" => 22,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001210",
            "country_rank" => 22,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001225",
            "country_rank" => 22,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001305",
            "country_rank" => 25,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001740",
            "country_rank" => 25,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001446",
            "country_rank" => 27,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001135",
            "country_rank" => 28,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001310",
            "country_rank" => 28,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001738",
            "country_rank" => 28,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001301",
            "country_rank" => 31,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001552",
            "country_rank" => 32,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001665",
            "country_rank" => 32,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001306",
            "country_rank" => 34,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001535",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001103",
            "country_rank" => 36,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001136",
            "country_rank" => 36,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001207",
            "country_rank" => 36,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001447",
            "country_rank" => 36,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001584",
            "country_rank" => 36,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001348",
            "country_rank" => 41,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001516",
            "country_rank" => 41,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001571",
            "country_rank" => 43,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001311",
            "country_rank" => 44,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001383",
            "country_rank" => 44,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001206",
            "country_rank" => 46,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001610",
            "country_rank" => 46,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001307",
            "country_rank" => 48,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001393",
            "country_rank" => 48,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001401",
            "country_rank" => 48,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001661",
            "country_rank" => 51,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001300",
            "country_rank" => 52,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001219",
            "country_rank" => 53,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001302",
            "country_rank" => 53,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001494",
            "country_rank" => 53,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001570",
            "country_rank" => 53,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001739",
            "country_rank" => 53,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001029",
            "country_rank" => 58,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001093",
            "country_rank" => 58,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001312",
            "country_rank" => 60,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001697",
            "country_rank" => 61,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001027",
            "country_rank" => 62,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001132",
            "country_rank" => 62,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001204",
            "country_rank" => 62,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001625",
            "country_rank" => 62,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001026",
            "country_rank" => 66,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001304",
            "country_rank" => 66,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001536",
            "country_rank" => 66,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001031",
            "country_rank" => 69,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001590",
            "country_rank" => 69,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001223",
            "country_rank" => 71,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001667",
            "country_rank" => 71,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001660",
            "country_rank" => 73,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001030",
            "country_rank" => 74,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001134",
            "country_rank" => 74,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001725",
            "country_rank" => 74,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001037",
            "country_rank" => 77,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001208",
            "country_rank" => 77,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001221",
            "country_rank" => 77,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001773",
            "country_rank" => 77,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001025",
            "country_rank" => 81,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001126",
            "country_rank" => 82,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001169",
            "country_rank" => 82,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001224",
            "country_rank" => 82,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001450",
            "country_rank" => 82,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001299",
            "country_rank" => 86,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001569",
            "country_rank" => 86,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001663",
            "country_rank" => 86,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001033",
            "country_rank" => 89,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001220",
            "country_rank" => 89,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001451",
            "country_rank" => 89,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001666",
            "country_rank" => 89,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001664",
            "country_rank" => 93,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001034",
            "country_rank" => 94,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001036",
            "country_rank" => 94,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001038",
            "country_rank" => 94,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001170",
            "country_rank" => 94,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001222",
            "country_rank" => 94,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001452",
            "country_rank" => 94,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001110",
            "country_rank" => 100,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001133",
            "country_rank" => 101,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001737",
            "country_rank" => 101,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001032",
            "country_rank" => 103,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001039",
            "country_rank" => 103,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001094",
            "country_rank" => 103,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001024",
            "country_rank" => 106,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001686",
            "country_rank" => 106,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001028",
            "country_rank" => 108,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001035",
            "country_rank" => 108,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001578",
            "country_rank" => 110,
            "school_rank" => 13,
        ],
        [
            "index" => "066231001659",
            "country_rank" => 111,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001082",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001775",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001081",
            "country_rank" => 3,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001322",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001354",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001413",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001218",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001703",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001616",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001177",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001582",
            "country_rank" => 10,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001231",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001353",
            "country_rank" => 12,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001551",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001355",
            "country_rank" => 15,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001597",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001776",
            "country_rank" => 17,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001698",
            "country_rank" => 18,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001181",
            "country_rank" => 19,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001706",
            "country_rank" => 20,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001321",
            "country_rank" => 21,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001495",
            "country_rank" => 21,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001316",
            "country_rank" => 23,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001549",
            "country_rank" => 24,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001611",
            "country_rank" => 25,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001453",
            "country_rank" => 26,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001742",
            "country_rank" => 26,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001465",
            "country_rank" => 28,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001371",
            "country_rank" => 29,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001671",
            "country_rank" => 30,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001041",
            "country_rank" => 31,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001106",
            "country_rank" => 31,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001456",
            "country_rank" => 31,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001550",
            "country_rank" => 31,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001741",
            "country_rank" => 35,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001211",
            "country_rank" => 36,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001217",
            "country_rank" => 36,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001498",
            "country_rank" => 36,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001700",
            "country_rank" => 36,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001138",
            "country_rank" => 40,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001701",
            "country_rank" => 40,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001040",
            "country_rank" => 42,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001216",
            "country_rank" => 42,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001699",
            "country_rank" => 42,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001095",
            "country_rank" => 45,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001579",
            "country_rank" => 45,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001607",
            "country_rank" => 45,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001080",
            "country_rank" => 48,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001183",
            "country_rank" => 48,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001234",
            "country_rank" => 50,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001313",
            "country_rank" => 50,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001543",
            "country_rank" => 50,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001705",
            "country_rank" => 50,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001320",
            "country_rank" => 54,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001214",
            "country_rank" => 55,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001303",
            "country_rank" => 55,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001331",
            "country_rank" => 55,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001339",
            "country_rank" => 55,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001744",
            "country_rank" => 55,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001670",
            "country_rank" => 60,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001194",
            "country_rank" => 61,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001213",
            "country_rank" => 61,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001227",
            "country_rank" => 61,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001460",
            "country_rank" => 61,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001462",
            "country_rank" => 61,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001606",
            "country_rank" => 61,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001154",
            "country_rank" => 67,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001212",
            "country_rank" => 67,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001229",
            "country_rank" => 67,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001325",
            "country_rank" => 67,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001707",
            "country_rank" => 67,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001044",
            "country_rank" => 72,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001182",
            "country_rank" => 72,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001195",
            "country_rank" => 72,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001375",
            "country_rank" => 75,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001215",
            "country_rank" => 76,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001315",
            "country_rank" => 76,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001384",
            "country_rank" => 76,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001454",
            "country_rank" => 76,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001702",
            "country_rank" => 76,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001189",
            "country_rank" => 81,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001314",
            "country_rank" => 81,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001324",
            "country_rank" => 81,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001458",
            "country_rank" => 81,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001527",
            "country_rank" => 81,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001704",
            "country_rank" => 81,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001743",
            "country_rank" => 81,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001228",
            "country_rank" => 88,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001233",
            "country_rank" => 88,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001318",
            "country_rank" => 88,
            "school_rank" => 11,
        ],
        [
            "index" => "066231001455",
            "country_rank" => 88,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001463",
            "country_rank" => 88,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001172",
            "country_rank" => 93,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001180",
            "country_rank" => 93,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001186",
            "country_rank" => 93,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001193",
            "country_rank" => 93,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001593",
            "country_rank" => 93,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001537",
            "country_rank" => 98,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001230",
            "country_rank" => 99,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001323",
            "country_rank" => 99,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001327",
            "country_rank" => 99,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001496",
            "country_rank" => 99,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001668",
            "country_rank" => 99,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001190",
            "country_rank" => 104,
            "school_rank" => 12,
        ],
        [
            "index" => "066231001317",
            "country_rank" => 104,
            "school_rank" => 14,
        ],
        [
            "index" => "066231001173",
            "country_rank" => 106,
            "school_rank" => 13,
        ],
        [
            "index" => "066231001179",
            "country_rank" => 106,
            "school_rank" => 13,
        ],
        [
            "index" => "066231001319",
            "country_rank" => 106,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001043",
            "country_rank" => 109,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001137",
            "country_rank" => 109,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001326",
            "country_rank" => 109,
            "school_rank" => 16,
        ],
        [
            "index" => "066231001045",
            "country_rank" => 112,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001174",
            "country_rank" => 112,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001184",
            "country_rank" => 112,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001192",
            "country_rank" => 112,
            "school_rank" => 15,
        ],
        [
            "index" => "066231001459",
            "country_rank" => 112,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001669",
            "country_rank" => 112,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001687",
            "country_rank" => 112,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001596",
            "country_rank" => 119,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001042",
            "country_rank" => 120,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001175",
            "country_rank" => 120,
            "school_rank" => 18,
        ],
        [
            "index" => "066231001188",
            "country_rank" => 120,
            "school_rank" => 18,
        ],
        [
            "index" => "066231001235",
            "country_rank" => 120,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001548",
            "country_rank" => 120,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001592",
            "country_rank" => 120,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001046",
            "country_rank" => 126,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001096",
            "country_rank" => 126,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001171",
            "country_rank" => 126,
            "school_rank" => 20,
        ],
        [
            "index" => "066231001178",
            "country_rank" => 126,
            "school_rank" => 20,
        ],
        [
            "index" => "066231001187",
            "country_rank" => 126,
            "school_rank" => 20,
        ],
        [
            "index" => "066231001232",
            "country_rank" => 126,
            "school_rank" => 9,
        ],
        [
            "index" => "066231001528",
            "country_rank" => 126,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001176",
            "country_rank" => 133,
            "school_rank" => 23,
        ],
        [
            "index" => "066231001185",
            "country_rank" => 133,
            "school_rank" => 23,
        ],
        [
            "index" => "066231001191",
            "country_rank" => 133,
            "school_rank" => 23,
        ],
        [
            "index" => "066231001367",
            "country_rank" => 133,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001591",
            "country_rank" => 133,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001602",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001780",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001356",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001779",
            "country_rank" => 4,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001572",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001778",
            "country_rank" => 6,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001472",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001777",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001595",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001471",
            "country_rank" => 10,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001141",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001501",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001104",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001414",
            "country_rank" => 13,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001050",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001708",
            "country_rank" => 16,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001470",
            "country_rank" => 16,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001139",
            "country_rank" => 18,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001051",
            "country_rank" => 19,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001617",
            "country_rank" => 20,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001467",
            "country_rank" => 21,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001378",
            "country_rank" => 22,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001402",
            "country_rank" => 23,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001236",
            "country_rank" => 23,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001097",
            "country_rank" => 23,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001748",
            "country_rank" => 26,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001047",
            "country_rank" => 26,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001600",
            "country_rank" => 26,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001747",
            "country_rank" => 29,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001745",
            "country_rank" => 29,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001328",
            "country_rank" => 29,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001672",
            "country_rank" => 29,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001147",
            "country_rank" => 29,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001709",
            "country_rank" => 34,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001048",
            "country_rank" => 34,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001556",
            "country_rank" => 34,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001473",
            "country_rank" => 37,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001142",
            "country_rank" => 37,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001403",
            "country_rank" => 39,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001594",
            "country_rank" => 40,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001500",
            "country_rank" => 40,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001049",
            "country_rank" => 40,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001140",
            "country_rank" => 43,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001468",
            "country_rank" => 43,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001746",
            "country_rank" => 45,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001469",
            "country_rank" => 45,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001502",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001783",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001781",
            "country_rank" => 3,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001404",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001330",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001787",
            "country_rank" => 6,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001786",
            "country_rank" => 6,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001478",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001784",
            "country_rank" => 9,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001785",
            "country_rank" => 10,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001240",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001477",
            "country_rank" => 12,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001782",
            "country_rank" => 13,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001053",
            "country_rank" => 14,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001750",
            "country_rank" => 15,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001058",
            "country_rank" => 16,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001146",
            "country_rank" => 16,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001055",
            "country_rank" => 18,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001364",
            "country_rank" => 18,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001557",
            "country_rank" => 20,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001518",
            "country_rank" => 21,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001618",
            "country_rank" => 22,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001580",
            "country_rank" => 23,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001143",
            "country_rank" => 24,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001749",
            "country_rank" => 24,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001237",
            "country_rank" => 24,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001619",
            "country_rank" => 27,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001329",
            "country_rank" => 27,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001379",
            "country_rank" => 27,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001688",
            "country_rank" => 27,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001052",
            "country_rank" => 31,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001059",
            "country_rank" => 32,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001620",
            "country_rank" => 33,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001474",
            "country_rank" => 33,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001475",
            "country_rank" => 35,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001575",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001203",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001530",
            "country_rank" => 38,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001238",
            "country_rank" => 39,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001752",
            "country_rank" => 39,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001151",
            "country_rank" => 41,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001628",
            "country_rank" => 41,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001056",
            "country_rank" => 43,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001673",
            "country_rank" => 44,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001054",
            "country_rank" => 44,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001098",
            "country_rank" => 46,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001621",
            "country_rank" => 46,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001751",
            "country_rank" => 46,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001144",
            "country_rank" => 49,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001149",
            "country_rank" => 49,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001239",
            "country_rank" => 51,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001346",
            "country_rank" => 51,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001476",
            "country_rank" => 51,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001148",
            "country_rank" => 54,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001332",
            "country_rank" => 54,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001145",
            "country_rank" => 54,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001573",
            "country_rank" => 57,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001150",
            "country_rank" => 57,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001503",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001788",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001244",
            "country_rank" => 3,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001381",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001241",
            "country_rank" => 5,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001415",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001481",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001753",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001612",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001515",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001062",
            "country_rank" => 11,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001246",
            "country_rank" => 12,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001505",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001487",
            "country_rank" => 12,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001243",
            "country_rank" => 15,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001406",
            "country_rank" => 16,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001755",
            "country_rank" => 17,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001675",
            "country_rank" => 17,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001482",
            "country_rank" => 17,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001480",
            "country_rank" => 17,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001060",
            "country_rank" => 21,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001483",
            "country_rank" => 21,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001061",
            "country_rank" => 23,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001063",
            "country_rank" => 23,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001479",
            "country_rank" => 23,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001485",
            "country_rank" => 23,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001581",
            "country_rank" => 27,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001242",
            "country_rank" => 27,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001486",
            "country_rank" => 29,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001754",
            "country_rank" => 29,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001484",
            "country_rank" => 29,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001492",
            "country_rank" => 32,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001676",
            "country_rank" => 32,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001099",
            "country_rank" => 34,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001369",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001152",
            "country_rank" => 35,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001489",
            "country_rank" => 37,
            "school_rank" => 10,
        ],
        [
            "index" => "066231001497",
            "country_rank" => 37,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001405",
            "country_rank" => 37,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001245",
            "country_rank" => 37,
            "school_rank" => 6,
        ],
        [
            "index" => "066231001674",
            "country_rank" => 41,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001789",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001350",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001790",
            "country_rank" => 3,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001424",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001791",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001493",
            "country_rank" => 6,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001599",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001417",
            "country_rank" => 8,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001507",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001710",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001420",
            "country_rank" => 11,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001510",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001112",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001361",
            "country_rank" => 14,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001422",
            "country_rank" => 15,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001418",
            "country_rank" => 16,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001423",
            "country_rank" => 16,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001421",
            "country_rank" => 18,
            "school_rank" => 7,
        ],
        [
            "index" => "066231001100",
            "country_rank" => 19,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001114",
            "country_rank" => 20,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001115",
            "country_rank" => 21,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001386",
            "country_rank" => 22,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001763",
            "country_rank" => 22,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001113",
            "country_rank" => 24,
            "school_rank" => 4,
        ],
        [
            "index" => "066231001419",
            "country_rank" => 25,
            "school_rank" => 8,
        ],
        [
            "index" => "066231001387",
            "country_rank" => 25,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001757",
            "country_rank" => 25,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001585",
            "country_rank" => 28,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001756",
            "country_rank" => 28,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001349",
            "country_rank" => 30,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001111",
            "country_rank" => 30,
            "school_rank" => 5,
        ],
        [
            "index" => "066231001793",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001792",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001377",
            "country_rank" => 3,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001508",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001385",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001426",
            "country_rank" => 5,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001116",
            "country_rank" => 7,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001506",
            "country_rank" => 8,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001504",
            "country_rank" => 9,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001555",
            "country_rank" => 10,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001425",
            "country_rank" => 11,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001357",
            "country_rank" => 12,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001428",
            "country_rank" => 13,
            "school_rank" => 3,
        ],
        [
            "index" => "066231001759",
            "country_rank" => 14,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001758",
            "country_rank" => 15,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001794",
            "country_rank" => 1,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001760",
            "country_rank" => 2,
            "school_rank" => 1,
        ],
        [
            "index" => "066231001761",
            "country_rank" => 3,
            "school_rank" => 2,
        ],
        [
            "index" => "066231001429",
            "country_rank" => 4,
            "school_rank" => 1,
        ],
    ];
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach($this->data as $participantData) {
            CompetitionParticipantsResults::where('participant_index', $participantData['index'])
                ->update([
                    'country_rank'  => $participantData['country_rank'],
                    'school_rank'   => $participantData['school_rank']
                ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
