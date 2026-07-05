<?php

namespace App\Http\Controllers;

use App\Helpers\ImageCompress;
use App\Helpers\Response;
use App\Http\Resources\DetailTubeWithTubeBarcodeResource;
use App\Models\Image;
use App\Models\Tube;
use App\Models\TubeBarcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TubeBarcodeManagementController extends Controller
{
    public function index(Request $r)
    {
        $r->validate([
            'search' => 'bail|nullable|string|max:50',
            'paginate' => 'bail|nullable|integer|min:1'
        ]);

        try {
            $_tubes = Tube::when($r->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('number', 'like', '%'.$search.'%')
                    ->orWhereRelation('latestTubeBarcode', 'barcode', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('number');
            if ($r->filled('paginate')) {
                $tubes = $_tubes->paginate($r->integer('paginate'));
            } else {
                $tubes = $_tubes->get();
            }
            return DetailTubeWithTubeBarcodeResource::collection($tubes);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r)
    {
        $r->validate([
            'tube_barcodes' => 'bail|required|array',
            'tube_barcodes.*.tube_uid' => 'bail|required|exists:tubes,uid',
            'tube_barcodes.*.barcode' => 'bail|required|string|max:50',
            'tube_barcodes.*.photo' => 'bail|nullable|image|mimes:png,jpg,jpeg|max:10240'
        ], [
            'tube_barcodes.required' => 'Input tidak sesuai',
            'tube_barcodes.*.tube_uid.required' => 'Input tidak sesuai',
            'tube_barcodes.*.barcode.required' => 'Masukkan kode barcode',
            'tube_barcodes.*.barcode.max' => 'Kode barcode maksimal 50 karakter',
            'tube_barcodes.*.photo.mimes' => 'Format foto tidak valid. Gunakan format png atau jpg',
            'tube_barcodes.*.photo.max' => 'Ukuran foto maksimal 10MB'
        ]);

        DB::beginTransaction();
        try {
            $tubeBarcodes = $r->input('tube_barcodes');
            foreach ($tubeBarcodes as $key => $tubeBarcode) {
                $tube = Tube::where('uid', $tubeBarcode['tube_uid'])->firstOrFail();
                if ($tube->barcode != $tubeBarcode['barcode']) {
                    $barcode = new TubeBarcode;
                    $barcode->tube()->associate($tube);
                    $barcode->barcode = $tubeBarcode['barcode'];
                    $barcode->save();

                    if (!$r->hasFile("tube_barcodes.{$key}.photo")) {
                        return Response::validation([
                            "tube_barcodes.{$key}.photo" => ['Masukkan foto tabung']
                        ]);
                    }

                    $optImage = ImageCompress::compress(
                        $r->file("tube_barcodes.{$key}.photo"),
                        maxDimension: 2048,
                        quality: 85
                    );

                    $_photo = Storage::disk('images')->put('tube', $optImage);
                    $photo = new Image;
                    $photo->imageable()->associate($barcode);
                    $photo->path = $_photo;
                    $photo->type = 'tube';
                    $photo->save();
                } else {
                    if ($r->hasFile("tube_barcodes.{$key}.photo")) {
                        $tube->latestTubeBarcode?->photo?->delete();
                        $optImage = ImageCompress::compress(
                            $r->file("tube_barcodes.{$key}.photo"),
                            maxDimension: 2048,
                            quality: 85
                        );

                        $_photo = Storage::disk('images')->put('tube', $optImage);
                        $photo = new Image;
                        $photo->imageable()->associate($tube->latestTubeBarcode);
                        $photo->path = $_photo;
                        $photo->type = 'tube';
                        $photo->save();
                    }
                }
            }
            DB::commit();
            return Response::updated();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
