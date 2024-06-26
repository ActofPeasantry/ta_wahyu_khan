<?php

namespace App\Models;

use CodeIgniter\Model;

class Pembayaran extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'pembayarans';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['tanggal_pembayaran', 'is_deleted', 'jumlah_tenda', 'bukti_pembayaran', 'sudah_bayar', 'catatan', 'alamat_kirim', 'tanggal_mulai_sewa', 'penyewa_id', 'tenda_id'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];


    public function getUnpaidPembayaran($penyewaId)
    {
        $this->select('pembayarans.*')
            ->where([
                'pembayarans.is_deleted' => 0,
                'pembayarans.penyewa_id' => $penyewaId,
                'pembayarans.tanggal_pembayaran' => NULL,
                'pembayarans.bukti_pembayaran' => NULL,
                'pembayarans.sudah_bayar' => 0,
            ])
            ->orderBy('pembayarans.id', 'asc');
        return $this;
    }

    public function getUnpaidPembayaranCost($penyewaId)
    {
        // Get unpaid pembayaran for the given penyewaId
        $getPembayarans = $this->getUnpaidPembayaran($penyewaId)->get()->getResultArray();

        if (!empty($getPembayarans)) {
            $total_cost_data = [];
            foreach ($getPembayarans as $getPembayaran) {
                // Retrieve the first unpaid pembayaran ID
                $pembayaranId = $getPembayaran['id'];

                // Initialize DetailPembayaran model
                $getDetails = new DetailPembayaran();

                // Perform join with 'tendas' table
                $getCosts = $getDetails
                    ->select('detail_pembayarans.*, tendas.harga') // Select desired columns
                    ->join('tendas', 'detail_pembayarans.tenda_id = tendas.id') // Join 'tendas' table
                    ->where('detail_pembayarans.pembayaran_id', $pembayaranId) // Filter by pembayaran_id
                    ->get()
                    ->getResultArray();

                // Calculate total cost based on retrieved data
                $total_cost = 0;
                foreach ($getCosts as $cost) {
                    $total_cost += $cost['lama_sewa'] * $cost['jumlah_tenda'] * $cost['harga'];
                }
                array_push($total_cost_data, $total_cost);
            }
            return $total_cost_data;
        }
        return 0; // Return 0 if no unpaid pembayaran found
    }

    public function getPembayaranBelumBayarWithTenda($penyewaId)
    {
        $this->select('pembayarans.*, tendas.kode, tendas.nama, tendas.ukuran, tendas.harga, tendas.sisa, tendas.gambar, tendas.kategori_id');

        $this->join('tendas', 'pembayarans.tenda_id = tendas.id');

        $this->where('pembayarans.is_deleted', 0);
        $this->where('pembayarans.penyewa_id', $penyewaId);

        $this->where('tanggal_pembayaran IS NULL');
        $this->where('bukti_pembayaran IS NULL');

        $this->orderBy('pembayarans.id', 'asc');

        return $this;
    }

    public function getPembayaranSudahBayarWithTenda($penyewaId)
    {
        $this->select('pembayarans.*, tendas.kode, tendas.nama, tendas.ukuran, tendas.harga, tendas.sisa, tendas.gambar, tendas.kategori_id');

        $this->join('tendas', 'pembayarans.tenda_id = tendas.id');

        $this->where('pembayarans.is_deleted', 0);
        $this->where('pembayarans.penyewa_id', $penyewaId);

        $this->where('tanggal_pembayaran IS NOT NULL');
        $this->where('bukti_pembayaran IS NOT NULL');

        $this->orderBy('pembayarans.id', 'asc');

        return $this;
    }

    public function getPembayaranWithTendaByPenyewaAndStatus($penyewaId, $status)
    {
        $this->select('pembayarans.*, tendas.kode, tendas.nama, tendas.ukuran, tendas.harga, tendas.sisa, tendas.gambar, tendas.kategori_id');

        $this->join('tendas', 'pembayarans.tenda_id = tendas.id');

        $this->where('pembayarans.is_deleted', 0);

        $this->where('pembayarans.penyewa_id', $penyewaId);

        $this->where('pembayarans.sudah_bayar', $status);

        $this->orderBy('pembayarans.bukti_pembayaran', 'asc');
        $this->orderBy('pembayarans.id', 'asc');

        return $this;
    }

    public function getPembayaranByPembayaranIdList($pembayaranIdList)
    {
        $this->select('pembayarans.*, detail_pembayarans.*, 
        tendas.kode, tendas.nama, tendas.ukuran, tendas.harga, tendas.sisa, tendas.gambar, tendas.kategori_id')
            ->join('detail_pembayarans', 'detail_pembayarans.pembayaran_id = pembayarans.id')
            ->join('tendas', 'detail_pembayarans.tenda_id = tendas.id')
            ->where('pembayarans.is_deleted', 0)
            ->whereIn('pembayarans.id', $pembayaranIdList)
            ->orderBy('pembayarans.bukti_pembayaran', 'asc')
            ->orderBy('pembayarans.id', 'asc');

        return $this;
    }

    public function getPembayaranByStatus($status)
    {
        $this->select('pembayarans.*, tendas.nama AS nama_tenda, tendas.harga AS harga_tenda, penyewas.nama AS nama_penyewa, ');

        $this->join('tendas', 'pembayarans.tenda_id = tendas.id');
        $this->join('penyewas', 'pembayarans.penyewa_id = penyewas.id');

        $this->where('pembayarans.is_deleted', 0);
        $this->where('pembayarans.sudah_bayar', $status);

        $this->orderBy('pembayarans.bukti_pembayaran', 'asc');
        $this->orderBy('pembayarans.id', 'asc');

        return $this;
    }

    public function detailPembayarans()
    {
        return $this->hasMany(detailPembayaran::class, 'pembayaran_id', 'id');
    }

    public function penyewas()
    {
        return $this->belongsTo(Penyewa::class, 'penyewa_id', 'id');
    }
}
