<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
use App\Http\Resources\DetailTubeResource;
use App\Models\Image;
use App\Models\Tube;
use App\Models\TubeBarcode;
use App\Models\TubeContent;
use App\Models\TubeContentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TubeManagementController extends Controller
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
            return DetailTubeResource::collection($tubes);
        } catch (\Throwable $th) {
            return Response::internalError($th->getMessage());
        }
    }

    public function detail(string $uid)
    {
        $tube = Tube::where('uid', $uid)->firstOrFail();
        return new DetailTubeResource($tube);
    }

    public function create(Request $r)
    {
        $r->validate([
            'number' => 'bail|required|string|unique:tubes,number|max:50',
            'barcode' => 'bail|nullable|string|unique:tube_barcodes,barcode|max:50',
            'type' => 'bail|required|in:medical,industry',
            'content' => 'bail|required|exists:tube_content_types,uid',
            'own' => 'bail|required|boolean',
            'active' => 'bail|required|boolean',
            'photo' => 'bail|nullable|image|mimes:png,jpg,jpeg'
        ], [
            'number.required' => 'Masukkan nomor tabung',
            'number.unique' => 'Nomor tabung sudah digunakan',
            'number.max' => 'Nomor tabung maksimal 50 karakter',
            'barcode.unique' => 'Kode barcode sudah digunakan',
            'barcode.max' => 'Barcode maksimal 50 karakter',
            'type.required' => 'Tentukan jenis tabung',
            'content.required' => 'Tentukan isi tabung',
            'own.required' => 'Tentukan pemilik tabung',
            'active' => 'Tentukan status tabung',
            'photo.mimes' => 'Format foto tidak valid. Gunakan format png dan jpg'
        ]);

        DB::beginTransaction();
        try {
            $tube = new Tube;
            $tube->number = $r->input('number');
            $tube->type = $r->input('type');
            $tube->own = $r->input('own');
            $tube->active = $r->input('active');
            $tube->save();

            $content = new TubeContent;
            $content->tube()->associate($tube);
            $content->tubeContentType()->associate(TubeContentType::where('uid', $r->input('content'))->first());
            $content->save();

            if ($r->filled('barcode')) {
                $tubeBarcode = new TubeBarcode;
                $tubeBarcode->tube()->associate($tube);
                $tubeBarcode->barcode = $r->input('barcode');
                $tubeBarcode->save();
                if ($r->file('photo')) {
                    $_photo = Storage::disk('images')->put('tube', $r->file('photo'));
                    $photo = new Image;
                    $photo->imageable()->associate($tubeBarcode);
                    $photo->path = $_photo;
                    $photo->type = 'tube';
                    $photo->save();
                }
            }

            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function update(Request $r, string $uid)
    {
        $tube = Tube::where('uid', $uid)->firstOrFail();
        $r->validate([
            'number' => 'bail|required|string|max:50',
            'type' => 'bail|required|in:medical,industry',
            'content' => 'bail|required|exists:tube_content_types,uid',
            'own' => 'bail|required|boolean',
            'active' => 'bail|required|boolean'
        ], [
            'number.required' => 'Masukkan nomor tabung',
            'number.max' => 'Nomor tabung maksimal 50 karakter',
            'type.required' => 'Tentukan jenis tabung',
            'content.required' => 'Tentukan isi tabung',
            'own.required' => 'Tentukan pemilik tabung',
            'active' => 'Tentukan status tabung'
        ]);

        DB::beginTransaction();
        try {
            $tube->number = $r->input('number');
            $tube->type = $r->input('type');
            $tube->own = $r->input('own');
            $tube->active = $r->input('active');
            $tube->save();

            $tubeContent = TubeContentType::where('uid', $r->input('content'))->first();
            $currentContent = $tube->latestTubeContent;
            if ($tubeContent->isNot($currentContent)) {
                $content = new TubeContent;
                $content->tube()->associate($tube);
                $content->tubeContentType()->associate($tubeContent);
                $content->save();
            }

            DB::commit();
            return Response::created();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }

    public function delete(string $uid)
    {
        $tube = Tube::where('uid', $uid)->firstOrFail();
        DB::beginTransaction();
        try {
            $tube->delete();
            DB::commit();
            return Response::deleted();
        } catch (\Throwable $th) {
            DB::rollBack();
            return Response::internalError($th->getMessage());
        }
    }
}
