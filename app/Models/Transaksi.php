<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'transaksis';

    protected $fillable = [
        'tanggal',
        'kategori_id',
        'metode_pembayaran_id',
        'area_id',
        'user_id',
        'keterangan',
        'debet',
        'kredit',
        'nota'
    ];

    // Relasi ke Kategori
    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    // Relasi ke Metode Pembayaran
    public function metodePembayaran()
    {
        return $this->belongsTo(MetodePembayaran::class);
    }

    // Relasi ke User/Teknisi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Area
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}