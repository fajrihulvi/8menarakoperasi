<?php wajib_akses('histori_barang'); ?>
<table class="w-full text-sm text-left border">
    <thead class="bg-gray-100">
        <tr>
            <th class="p-2">Tanggal</th>
            <th class="p-2">Nama Barang</th>
            <th class="p-2">Qty</th>
            <th class="p-2">Satuan</th>
            <th class="p-2">No Faktur</th>
            <th class="p-2">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $q = mysqli_query($conn, "QUERY_SQL_DI_ATAS");
        while($r = mysqli_fetch_assoc($q)): ?>
        <tr class="border-b">
            <td class="p-2"><?= date('d/m/Y H:i', strtotime($r['tanggal'])) ?></td>
            <td class="p-2"><?= $r['nama_barang'] ?></td>
            <td class="p-2"><?= (float)$r['qty'] ?></td>
            <td class="p-2"><?= $r['satuan'] ?></td>
            <td class="p-2"><b><?= $r['no_faktur'] ?></b></td>
            <td class="p-2"><?= $r['keterangan'] ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>