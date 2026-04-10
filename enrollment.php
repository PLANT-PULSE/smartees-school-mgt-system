<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/functions.php';

requireLogin();
$pageTitle = 'Student Enrollment';
$pdo = getDb();

// Initialize services
$studentService = new StudentService($pdo);

// Get classes
$stmt = $pdo->query('SELECT id, name FROM classes ORDER BY name');
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$action = $_GET['action'] ?? 'form';
$enrollmentStep = intval($_POST['step'] ?? 1);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Step 1: Student Information
        $studentData = [
            'name' => trim($_POST['student_name']),
            'age' => intval($_POST['age']),
            'contact' => trim($_POST['student_contact']),
            'class_id' => intval($_POST['class_id'])
        ];

        // Validate student data
        if (empty($studentData['name']) || $studentData['class_id'] <= 0) {
            throw new Exception('Student name and class are required.');
        }

        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/students/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
            $targetFile = $uploadDir . $fileName;

            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['photo']['type'], $allowedTypes)) {
                throw new Exception('Only JPG, PNG, and GIF files are allowed.');
            }

            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size must be less than 5MB.');
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                $studentData['photo'] = 'uploads/students/' . $fileName;
            }
        }

        // Step 2: Parent/Guardian Information
        $parentData = [];
        if (!empty($_POST['parent_first_name'])) {
            $parentData = [
                'first_name' => trim($_POST['parent_first_name']),
                'last_name' => trim($_POST['parent_last_name']),
                'relationship' => trim($_POST['parent_relationship']),
                'email' => trim($_POST['parent_email']),
                'phone' => trim($_POST['parent_phone']),
                'address' => trim($_POST['parent_address']),
                'city' => trim($_POST['parent_city']),
                'state' => trim($_POST['parent_state']),
                'zip_code' => trim($_POST['parent_zip_code']),
                'occupation' => trim($_POST['parent_occupation']),
                'is_primary_contact' => isset($_POST['is_primary_contact'])
            ];
        }

        // Step 3: Contact Information
        $contactData = [
            'emergency_contact_name' => trim($_POST['emergency_contact_name']),
            'emergency_contact_phone' => trim($_POST['emergency_contact_phone']),
            'emergency_contact_relation' => trim($_POST['emergency_contact_relation']),
            'medical_condition' => trim($_POST['medical_condition']),
            'allergies' => trim($_POST['allergies']),
            'blood_group' => trim($_POST['blood_group'])
        ];

        // Enroll student
        $studentId = $studentService->enrollStudent($studentData, $parentData ? [$parentData] : [], $contactData);

        flash('success', 'Student enrolled successfully! Student ID: ' . $studentId);
        redirect('enrollment.php?action=form&student_id=' . $studentId);
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        redirect('enrollment.php?action=form');
    }
}

?>
<?php include __DIR__ . '/inc/sidebar-header.php'; ?>

<div class="container mt-4 mb-5">
    <h1 class="mb-4">
        <i class="fas fa-user-plus me-2"></i>Student Enrollment
    </h1>

    <?php if ($msg = flash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Multi-Step Form -->
    <div class="row">
        <div class="col-lg-8">
            <form method="post" action="enrollment.php" enctype="multipart/form-data" id="enrollmentForm">
                <div class="card shadow-sm mb-4">
                    <!-- Progress Bar -->
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" href="#studentInfo" data-bs-toggle="tab">
                                    <span class="badge bg-primary">1</span> Student Info
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#parentInfo" data-bs-toggle="tab">
                                    <span class="badge bg-secondary">2</span> Parent/Guardian
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#contactInfo" data-bs-toggle="tab">
                                    <span class="badge bg-secondary">3</span> Contact Details
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                        <!-- TAB 1: Student Information -->
                        <div class="tab-pane fade show active" id="studentInfo">
                            <div class="card-body">
                                <h5 class="mb-4">
                                    <i class="fas fa-id-badge me-2"></i>Student Information
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="student_name" class="form-control" required placeholder="e.g., John Doe">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Age</label>
                                        <input type="number" name="age" class="form-control" min="1" max="25" placeholder="e.g., 10">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gender</label>
                                        <select name="gender" class="form-control">
                                            <option value="">-- Select --</option>
                                            <option value="M">Male</option>
                                            <option value="F">Female</option>
                                            <option value="O">Other</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Class <span class="text-danger">*</span></label>
                                        <select name="class_id" class="form-control" required>
                                            <option value="">-- Select Class --</option>
                                            <?php foreach ($classes as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Student Contact</label>
                                        <input type="email" name="student_contact" class="form-control" placeholder="e.g., student@example.com">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Student Photo</label>
                                        <input type="file" name="photo" class="form-control" accept="image/*">
                                        <small class="text-muted">Max 5MB. Accepted: JPG, PNG, GIF</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: Parent/Guardian Information -->
                        <div class="tab-pane fade" id="parentInfo">
                            <div class="card-body">
                                <h5 class="mb-4">
                                    <i class="fas fa-user-tie me-2"></i>Parent/Guardian Information
                                </h5>

                                <div class="border-bottom pb-3 mb-3">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="hasParent" checked onchange="toggleParentForm()">
                                        <label class="form-check-label" for="hasParent">
                                            Add Parent/Guardian Information
                                        </label>
                                    </div>
                                </div>

                                <div id="parentFormContainer">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name</label>
                                            <input type="text" name="parent_first_name" class="form-control" placeholder="e.g., John">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name</label>
                                            <input type="text" name="parent_last_name" class="form-control" placeholder="e.g., Cooper">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Relationship</label>
                                            <select name="parent_relationship" class="form-control">
                                                <option value="">-- Select --</option>
                                                <option value="Father">Father</option>
                                                <option value="Mother">Mother</option>
                                                <option value="Guardian">Guardian</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                <input type="checkbox" name="is_primary_contact" value="1">
                                                Primary Contact
                                            </label>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="parent_email" class="form-control" placeholder="parent@example.com">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" name="parent_phone" class="form-control" placeholder="e.g., +234-xxx-xxx-xxxx">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Address</label>
                                            <input type="text" name="parent_address" class="form-control" placeholder="Street address">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">City</label>
                                            <input type="text" name="parent_city" class="form-control">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">State</label>
                                            <input type="text" name="parent_state" class="form-control">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">ZIP Code</label>
                                            <input type="text" name="parent_zip_code" class="form-control">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Occupation</label>
                                            <input type="text" name="parent_occupation" class="form-control" placeholder="e.g., Engineer, Doctor">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: Contact & Medical Information -->
                        <div class="tab-pane fade" id="contactInfo">
                            <div class="card-body">
                                <h5 class="mb-4">
                                    <i class="fas fa-phone me-2"></i>Contact & Medical Information
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact Name</label>
                                        <input type="text" name="emergency_contact_name" class="form-control" placeholder="Full name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact Phone</label>
                                        <input type="tel" name="emergency_contact_phone" class="form-control" placeholder="Phone number">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Relationship</label>
                                        <select name="emergency_contact_relation" class="form-control">
                                            <option value="">-- Select --</option>
                                            <option value="Parent">Parent</option>
                                            <option value="Sibling">Sibling</option>
                                            <option value="Relative">Relative</option>
                                            <option value="Friend">Friend</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Blood Group</label>
                                        <select name="blood_group" class="form-control">
                                            <option value="">-- Select --</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Allergies</label>
                                        <input type="text" name="allergies" class="form-control" placeholder="e.g., Peanuts, Dairy, etc.">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Medical Conditions</label>
                                        <textarea name="medical_condition" class="form-control" rows="3" placeholder="Any medical conditions or special needs"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-check me-2"></i>Complete Enrollment
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sidebar: Help/Info -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Enrollment Tips
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <strong>Complete all required fields</strong> marked with *
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-image text-primary me-2"></i>
                            <strong>Upload a clear photo</strong> (max 5MB)
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-users text-warning me-2"></i>
                            <strong>Parent information</strong> helps us communicate important updates
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-heart text-danger me-2"></i>
                            <strong>Medical information</strong> is crucial for student safety
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-document-check me-2"></i>Required Documents
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Please ensure you have the following documents ready:
                    </p>
                    <ul class="list-unstyled mt-2">
                        <li>
                            <i class="fas fa-square text-secondary me-2"></i>Birth Certificate
                        </li>
                        <li>
                            <i class="fas fa-square text-secondary me-2"></i>Previous School Record
                        </li>
                        <li>
                            <i class="fas fa-square text-secondary me-2"></i>Parent ID/Proof
                        </li>
                        <li>
                            <i class="fas fa-square text-secondary me-2"></i>Medical Records
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleParentForm() {
    const checkbox = document.getElementById('hasParent');
    const container = document.getElementById('parentFormContainer');
    container.style.display = checkbox.checked ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/inc/sidebar-footer.php'; ?>
