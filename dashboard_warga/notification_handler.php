<?php
// notification_handler.php
require_once '../config/koneksi.php';

// Enable error logging
error_log("Midtrans Notification Received: " . date('Y-m-d H:i:s'));

// Ambil raw POST data
$input = file_get_contents('php://input');
$notification = json_decode($input, true);

if($notification) {
    error_log("Notification Data: " . json_encode($notification));
    
    $transaction_status = $notification['transaction_status'] ?? '';
    $order_id = $notification['order_id'] ?? '';
    $payment_type = $notification['payment_type'] ?? '';
    $transaction_id = $notification['transaction_id'] ?? '';
    
    if(empty($order_id)) {
        error_log("Error: Order ID is empty");
        echo "ERROR: Order ID is empty";
        exit;
    }
    
    // Update status pembayaran di database
    $query_update = "UPDATE pembayaran 
                    SET payment_status = ?,
                        payment_type = ?,
                        transaction_id = ?,
                        midtrans_data = ?,
                        updated_at = NOW()
                    WHERE order_id = ?";
    
    $stmt = $koneksi->prepare($query_update);
    $midtrans_data_json = json_encode($notification);
    
    $stmt->bind_param(
        "sssss",
        $transaction_status,
        $payment_type,
        $transaction_id,
        $midtrans_data_json,
        $order_id
    );
    
    if($stmt->execute()) {
        error_log("Database updated successfully for order: $order_id, status: $transaction_status");
        
        // Jika pembayaran berhasil, update status iuran
        if ($transaction_status == 'settlement' || $transaction_status == 'capture') {
            // Update status iuran menjadi lunas
            $query_update_iuran = "UPDATE iuran_rutin 
                                  SET status_pembayaran = 'Lunas',
                                      payment_status = 'settlement',
                                      tanggal_bayar = NOW()
                                  WHERE payment_order_id = ?";
            
            $stmt2 = $koneksi->prepare($query_update_iuran);
            $stmt2->bind_param("s", $order_id);
            $stmt2->execute();
            
            // Update status pembayaran
            $query_update_status = "UPDATE pembayaran 
                                   SET status = 'Lunas'
                                   WHERE order_id = ?";
            
            $stmt3 = $koneksi->prepare($query_update_status);
            $stmt3->bind_param("s", $order_id);
            $stmt3->execute();
            
            error_log("Iuran updated to Lunas for order: $order_id");
        }
        
        echo "OK";
    } else {
        $error = mysqli_error($koneksi);
        error_log("Database update failed: $error");
        echo "ERROR: Database update failed";
    }
} else {
    error_log("Invalid notification data received");
    echo "ERROR: Invalid notification";
}
?>