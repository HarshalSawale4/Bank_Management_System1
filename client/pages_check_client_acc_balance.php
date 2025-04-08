<?php
session_start();
include('conf/config.php');
include('conf/checklogin.php');
check_login();
$client_id = $_SESSION['client_id'];

// Validate and sanitize account_id
if (!isset($_GET['account_id']) || empty($_GET['account_id'])) {
    die("Error: Account ID is missing.");
}
$account_id = intval($_GET['account_id']);

if (!$mysqli) {
    die("Database connection error: " . mysqli_connect_error());
}

// Function to get transaction sums
function getTransactionSum($mysqli, $account_id, $type)
{
    $query = "SELECT SUM(transaction_amt) FROM iB_Transactions WHERE account_id = ? AND tr_type = ?";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        die("Error in preparing statement: " . $mysqli->error);
    }
    $stmt->bind_param('is', $account_id, $type);
    $stmt->execute();
    $stmt->bind_result($amount);
    $stmt->fetch();
    $stmt->close();
    return $amount ?? 0; // Return 0 if NULL
}

// Get transaction totals
$deposit = getTransactionSum($mysqli, $account_id, 'Deposit');
$withdrawal = getTransactionSum($mysqli, $account_id, 'Withdrawal');
$transfer = getTransactionSum($mysqli, $account_id, 'Transfer');

// Fetch account details
$query = "SELECT * FROM iB_bankAccounts WHERE account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $account_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_object();
$stmt->close();

if (!$row) {
    die("Error: Account not found.");
}

// Compute values
$banking_rate = ($row->acc_rates) / 100;
$money_out = $withdrawal + $transfer;
$balance = $deposit - $money_out;
$rate_amt = $banking_rate * $balance;
$totalMoney = $rate_amt + $balance;
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html;charset=utf-8" />
    <?php include("dist/_partials/head.php"); ?>
</head>
<body>
    <div class="wrapper">
        <?php include("dist/_partials/nav.php"); ?>
        <?php include("dist/_partials/sidebar.php"); ?>
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <h1><?php echo $row->client_name; ?> Account Balance</h1>
                </div>
            </section>
            <section class="content">
                <div class="container-fluid">
                    <div id="balanceSheet" class="invoice p-3 mb-3">
                        <div class="row">
                            <h4><i class="fas fa-bank"></i> iBanking Corporation Balance Enquiry</h4>
                            <small class="float-right">Date: <?php echo date('d/m/Y'); ?></small>
                        </div>
                        <div class="row invoice-info">
                            <div class="col-sm-6">
                                <strong><?php echo $row->client_name; ?></strong><br>
                                <?php echo $row->client_email; ?><br>
                                Phone: <?php echo $row->client_phone; ?><br>
                            </div>
                            <div class="col-sm-6">
                                <strong><?php echo $row->acc_name; ?></strong><br>
                                Acc No: <?php echo $row->account_number; ?><br>
                                Acc Type: <?php echo $row->acc_type; ?><br>
                                Acc Rates: <?php echo $row->acc_rates; ?> %
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr><th>Deposits</th><td>$<?php echo $deposit; ?></td></tr>
                                <tr><th>Withdrawals</th><td>$<?php echo $withdrawal; ?></td></tr>
                                <tr><th>Transfers</th><td>$<?php echo $transfer; ?></td></tr>
                                <tr><th>Balance</th><td>$<?php echo $balance; ?></td></tr>
                                <tr><th>Interest</th><td>$<?php echo $rate_amt; ?></td></tr>
                                <tr><th>Total Balance</th><td>$<?php echo $totalMoney; ?></td></tr>
                            </table>
                        </div>
                        <button onclick="printContent('balanceSheet');" class="btn btn-success">Print</button>
                    </div>
                </div>
            </section>
        </div>
        <?php include("dist/_partials/footer.php"); ?>
    </div>
    <script>
        function printContent(el) {
            var printWindow = window.open('', '', 'height=500,width=800');
            printWindow.document.write('<html><head><title>Print</title></head><body>');
            printWindow.document.write(document.getElementById(el).innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
