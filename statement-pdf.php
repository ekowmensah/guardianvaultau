<?php
require('fpdf.php');
require_once __DIR__ . '/app_security.php';
start_secure_session();
include_once("db_conn.php");
require_user();
$userID = $_SESSION['loggedin'];

// Fetch all relevant details as in gold-deposit.php
// Item details
$stmtItem = $pdo->prepare("SELECT * FROM item_details WHERE user_id = ?");
$stmtItem->execute([$userID]);
$itemDetails = $stmtItem->fetch();
// Retrieve the next of kin details associated with the user (as in next-of-kin.php)
$stmtNextOfKin = $pdo->prepare("SELECT * FROM next_of_kin WHERE user_id = ? LIMIT 1");
$stmtNextOfKin->execute([$userID]);
$nextOfKin = $stmtNextOfKin->fetch() ?: [];
// State of items
$stmtState = $pdo->prepare("SELECT * FROM state_of_items WHERE user_id = ?");
$stmtState->execute([$userID]);
$stateOfItems = $stmtState->fetch();
// Fetch user (from users table, as in profile.php)
$stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, telephone_number, role, status, created_at, updated_at FROM users WHERE id = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();
// Fetch user profile (from userprofile table, as in profile.php)
$stmtProfile = $pdo->prepare("SELECT * FROM userprofile WHERE user_id = ?");
$stmtProfile->execute([$userID]);
$profile = $stmtProfile->fetch();

function pdf_text($value, int $maxLength = 90): string {
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
    if (mb_strlen($clean, 'UTF-8') > $maxLength) {
        $clean = mb_substr($clean, 0, $maxLength - 1, 'UTF-8') . '…';
    }
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $clean) ?: '-';
}

class PDF extends FPDF {
    var $angle = 0;
    var $statementId = '';
    function Header() {
        // Set document information
        $this->SetTitle('Guardian Vault - Account Statement');
        $this->SetAuthor('Guardian Vault');
        $this->SetCreator('Guardian Vault Banking System');

        // Top border
        $this->SetFillColor(0, 102, 204); // Blue
        $this->Rect(0, 0, $this->w, 4, 'F');
        
        // Main header
        $this->SetY(10);
        $this->SetFont('Arial','B',18);
        $this->SetTextColor(0, 51, 102); // Dark blue
        $this->Cell(0, 8, 'GUARDIAN VAULT', 0, 1, 'C');
        
        // Tagline
        $this->SetFont('Arial','I',9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Digital Gold Vault - Secure | Trusted | Accountable', 0, 1, 'C');
        
        // Statement title with underline
        $this->SetY(30);
        $this->SetFont('Arial','B',14);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 8, 'ACCOUNT STATEMENT', 0, 1, 'C');
        
        // Underline
        $this->SetDrawColor(0, 102, 204);
        $this->SetLineWidth(0.5);
        $this->Line(($this->w - 100) / 2, 40, ($this->w + 100) / 2, 40);
        
        // Statement info
        $this->SetY(45);
        $this->SetFont('Arial','',9);
        $this->SetTextColor(100, 100, 100);
        
        // Left-aligned info
        $this->SetX(20);
        $this->Cell(80, 5, 'Statement Date: ' . date('F j, Y'), 0, 0, 'L');
        
        // Right-aligned info
        $this->SetX(-90);
        $this->Cell(0, 5, 'Statement ID: ' . $this->statementId, 0, 1, 'L');
        
        // Divider line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(15, 55, $this->w-15, 55);
        
        // Start content below header
        $this->SetY(60);
        $this->SetTextColor(40, 40, 40);
    }
    // Draw a summary box (user info, statement date/id)
    function SummaryBox($user, $profile, $statementId) {
        $this->SetY(60);
        $this->SetFillColor(240, 248, 255);
        $this->SetDrawColor(33, 150, 243);
        $this->Rect(15, $this->GetY(), 180, 22, 'DF');
        $this->SetXY(20, $this->GetY()+3);
        $this->SetFont('Arial','B',12);
        $this->SetTextColor(27, 47, 89);
        $fullname = strtoupper(pdf_text(($profile['first_name'] ?? $user['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? $user['last_name'] ?? ''), 45));
        $this->Cell(60,6,$fullname,0,0,'L');
        $this->SetFont('Arial','',10);
        $this->SetTextColor(0,0,0);
        $this->Cell(40,6,'Username: '.strtoupper(pdf_text($user['username'] ?? '-', 24)),0,0,'L');
        $this->Cell(60,6,'Email: '.strtoupper(pdf_text($user['email'] ?? '-', 38)),0,1,'L');
        $this->SetXY(20, $this->GetY());
        $this->SetFont('Arial','B',10);
        $this->SetTextColor(255,255,255);
        $this->SetFillColor(108,117,125);
        $this->Cell(24,6,'ROLE',0,0,'C',true);
        $this->SetTextColor(27, 47, 89);
        $this->SetFont('Arial','',10);
        $this->Cell(30,6,strtoupper(pdf_text($user['role'] ?? '-', 20)),0,0,'L');
        $this->Cell(40,6,'Statement Date: '.date('F j, Y'),0,0,'L');
        $this->Cell(40,6,'Statement ID: '.strtoupper($statementId),0,1,'L');
        $this->Ln(2);
        $this->SetTextColor(40,40,40);
    }
    // Draw watermark on top of content
    function DrawWatermarkOnTop() {
        $this->SetFont('Arial','B',60);
        $this->SetTextColor(235,235,235);
        $this->RotatedText(35,160,'GUARDIAN VAULT',38);
    }
    // Card-style section with colored header and icon
    function CardSection($icon, $title, $rows) {
        $this->Ln(7);
        $w = 190;
        $tableRows = count($rows);
        $rowHeight = 10;
        $h = 18 + $tableRows * $rowHeight + 8;
        if ($this->GetY() + $h > $this->h - 20) {
            $this->AddPage();
            $this->DrawWatermarkOnTop();
        }
        // Card background
        $x = $this->GetX();
        $y = $this->GetY();
        $this->SetFillColor(255,255,255);
        $this->RoundedRect($x, $y, $w, $h, 6, 'F');
        // Section header
        $this->SetXY($x+2, $y+2);
        $this->SetFillColor(33, 150, 243);
        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','B',12);
        if (!empty($icon) && preg_match('/^[\x20-\x7E]+$/', $icon)) { // Only printable ASCII
            $this->Cell(10,12,$icon,0,0,'C',true);
        }
        $this->Cell(0,12,'  '.strtoupper($title),0,1,'L',true);
        // Table
        $this->SetFont('Arial','B',11);
        $this->SetTextColor(27, 47, 89);
        $this->SetY($y+16);
        $fill = false;
        foreach ($rows as $row) {
            $this->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 250 : 255);
            $this->Cell(60, $rowHeight, strtoupper($row[0]), 1, 0, 'L', true);
            $this->SetFont('Arial','',11);
            $this->Cell(128, $rowHeight, strtoupper(pdf_text($row[1])), 1, 1, 'L', true);
            $this->SetFont('Arial','B',11);
            $fill = !$fill;
        }
        $this->Ln(2);
    }

    // Draw a rounded rectangle
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-$y)*$k ));
        $this->_Arc($xc+$r*$MyArc, $yc-$r, $xc+$r, $yc-$r*$MyArc, $xc+$r, $yc);
        $xc = $x+$w-$r ; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$yc)*$k));
        $this->_Arc(($x+$w)-$r*$MyArc, $yc+$r, ($x+$w)-$r, $yc+$r*$MyArc, ($x+$w)-$r, $yc+$r);
        $xc = $x+$r ; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-($y+$h))*$k));
        $this->_Arc($xc-$r*$MyArc, $yc+$r, $xc-$r, $yc+$r*$MyArc, $xc-$r, $yc);
        $xc = $x+$r ; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $x*$k, ($hp-$yc)*$k ));
        $this->_Arc($xc-$r*$MyArc, $yc-$r, $xc-$r, $yc-$r*$MyArc, $xc-$r, $yc);
        $this->_out($op);
    }
    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k,
            $x3*$this->k, ($h-$y3)*$this->k));
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetTextColor(80, 80, 80);
        $this->SetFont('Arial','I',10);
        $this->Cell(0,8,'Guardian Vault | Receipt generated on '.date('jS F, Y, h:i A'),0,0,'C');
    }
    function RotatedText($x, $y, $txt, $angle) {
        // Text rotation for watermark
        $this->Rotate($angle,$x,$y);
        $this->Text($x,$y,$txt);
        $this->Rotate(0);
    }
    function Rotate($angle, $x = -1, $y = -1) {
        if($x == -1)
            $x = $this->x;
        if($y == -1)
            $y = $this->y;
        if($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if($angle != 0) {
            $angle *= M_PI/180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x*$this->k;
            $cy = ($this->h-$y)*$this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm', $c, $s, -$s, $c, $cx-$c*$cx+$s*$cy, $cy-$s*$cx-$c*$cy));
        }
    }
    function _endpage() {
        if($this->angle != 0) {
            $this->angle=0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
    function SectionTitle($label) {
        $this->SetFont('Arial','B',14);
        $this->SetTextColor(27, 47, 89);
        $this->Cell(0,10,$label,0,1,'L');
        $this->Ln(1);
    }
    function FancyRow($label, $value) {
        $this->SetFont('Arial','',12);
        $this->SetTextColor(60,60,60);
        $this->Cell(60,9,$label,0,0,'L');
        $this->SetFont('Arial','B',12);
        $this->SetTextColor(27, 47, 89);
        $this->Cell(0,9,$value,0,1,'L');
    }
    function TableSection($title, $rows) {
        $this->SectionTitle($title);
        foreach ($rows as $row) {
            $this->FancyRow($row[0], $row[1]);
        }
        $this->Ln(2);
    }
}

// End of PDF class

// Persist an immutable snapshot before rendering the issued statement.
$statementId = 'GV-' . gmdate('Ym') . '-' . strtoupper(bin2hex(random_bytes(8)));
$snapshot = [
    'user' => $user,
    'profile' => $profile,
    'item_details' => $itemDetails,
    'state_of_items' => $stateOfItems,
    'next_of_kin' => $nextOfKin,
    'issued_at_utc' => gmdate(DATE_ATOM),
];
$snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$snapshotHash = hash('sha256', $snapshotJson);
$signature = hash_hmac('sha256', $statementId . '|' . $userID . '|' . $snapshotHash, app_key());
$statementInsert = $pdo->prepare('INSERT INTO account_statements (statement_number, user_id, subject_user_id, snapshot, snapshot_hash, signature) VALUES (?, ?, ?, ?, ?, ?)');
$statementInsert->execute([$statementId, $userID, $userID, $snapshotJson, $snapshotHash, $signature]);
log_security_event('user', 'statement_issued', (int) $userID, (int) $userID, $statementId);

$pdf = new PDF();
$pdf->statementId = $statementId;
$pdf->AddPage();
$pdf->SummaryBox($user, $profile, $statementId);
$pdf->DrawWatermarkOnTop();
// Profile image logic
//$avatarFile = isset($profile['avatar']) && $profile['avatar'] !== '' ? $profile['avatar'] : 'user.png';
//$avatarPath = __DIR__ . '/assets/img/avatars/' . $avatarFile;
//if (!file_exists($avatarPath)) {
//    $avatarPath = __DIR__ . '/assets/img/avatars/user.png';
//}
//$pdf->Image($avatarPath, 15, 25, 30, 30);

// User Profile Section
$name = trim(($profile['first_name'] ?? $user['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? $user['last_name'] ?? ''));
$email = $profile['email'] ?? $user['email'] ?? '-';
$phone = $profile['phone'] ?? $user['phone'] ?? '-';
$profileRows = [
    ['Name', pdf_text($name)],
    ['Email', pdf_text($email)],
    //['Phone', htmlspecialchars($phone)],
];
$pdf->CardSection("\xF0\x9F\x91\xA4", 'Profile Details', $profileRows); // icon: user

// Item Details Section
$items = [
    ['icon' => 'fa-cube', 'label' => 'Box Dimension', 'field' => 'box_dimension'],
    ['icon' => 'fa-archive', 'label' => 'Deposited Item', 'field' => 'deposited_item'],
    ['icon' => 'fa-box', 'label' => 'Package Type', 'field' => 'package_type'],
    ['icon' => 'fa-boxes', 'label' => 'Package Quantity', 'field' => 'package_quantity'],
    ['icon' => 'fa-weight-hanging', 'label' => 'Total Weight', 'field' => 'total_weight', 'suffix' => ' kg'],
    ['icon' => 'fa-calendar-alt', 'label' => 'Deposit Date', 'field' => 'deposit_date', 'format_date' => true],
    ['icon' => 'fa-dollar-sign', 'label' => 'Monthly Charges', 'field' => 'monthly_charges', 'prefix' => 'AUD ', 'format_number' => true],
    ['icon' => 'fa-money-bill-wave', 'label' => 'Amount Paid', 'field' => 'amount_paid', 'prefix' => 'AUD ', 'format_number' => true],
];
$itemRows = [];
foreach ($items as $item) {
    $value = $itemDetails[$item['field']] ?? '-';
    if (!empty($item['format_number']) && $value !== '-') {
        $value = number_format($value, 2);
    }
    if (!empty($item['format_date']) && $value !== '-') {
        $value = date('jS F, Y', strtotime($value));
    }
    $value = (!empty($item['prefix']) ? $item['prefix'] : '') . $value . (!empty($item['suffix']) ? $item['suffix'] : '');
    $itemRows[] = [$item['label'], $value];
}
$pdf->CardSection('', 'Item Details', $itemRows); 

// State of Items Section
$pdf->CardSection('', 'State of Items', [
    ['Current Gold Worth', isset($stateOfItems['current_gold_worth']) ? 'AUD '.number_format($stateOfItems['current_gold_worth'],2) : '-'],
    ['Price per Kilogram', isset($stateOfItems['price_per_kilogram']) ? 'AUD '.number_format($stateOfItems['price_per_kilogram'],2) : '-'],
    ['Quantity', pdf_text($stateOfItems['quantity'] ?? '-')],
    ['Cost of Safe Keeping', isset($stateOfItems['cost_of_safe_keeping']) ? 'AUD '.number_format($stateOfItems['cost_of_safe_keeping'],2) : '-'],
    ['Date of Safe Keeping', isset($stateOfItems['date_of_safe_keeping']) ? date('jS F, Y', strtotime($stateOfItems['date_of_safe_keeping'])) : '-'],
]);

// Next of Kin Section (fields as in next-of-kin.php)
$pdf->CardSection('', 'Next of Kin', [
    ['Name of Beneficiary', pdf_text($nextOfKin['name_of_beneficial'] ?? '-')],
    ['Relationship', pdf_text($nextOfKin['relation_with_user'] ?? '-')],
    ['Date of Birth', isset($nextOfKin['date_of_birth']) ? date('jS F, Y', strtotime($nextOfKin['date_of_birth'])) : '-'],
    ['Phone', pdf_text($nextOfKin['telephone_number_kin'] ?? '-')],
    ['Email', pdf_text($nextOfKin['email_address'] ?? '-')],
   // ['Address', htmlspecialchars($nextOfKin['address'] ?? '-')],
]);

$pdfContent = $pdf->Output('S');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Guardian_Vault_' . $statementId . '.pdf"');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
echo $pdfContent;
exit;
