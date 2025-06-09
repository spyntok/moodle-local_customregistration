<?php
require('../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/moodlelib.php'); // for password hashing

class custom_registration_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required');

        $mform->addElement('text', 'firstname', get_string('firstname'));
        $mform->setType('firstname', PARAM_NOTAGS);
        $mform->addRule('firstname', null, 'required');

        $mform->addElement('text', 'lastname', get_string('lastname'));
        $mform->setType('lastname', PARAM_NOTAGS);
        $mform->addRule('lastname', null, 'required');

        $mform->addElement('select', 'country', get_string('country'), get_string_manager()->get_list_of_countries());
        $mform->addRule('country', null, 'required', null, 'client');

        $mform->addElement('text', 'phone', get_string('phone', 'local_customregistration'));
        $mform->setType('phone', PARAM_TEXT);

        $mform->addElement('submit', 'submitbutton', get_string('register', 'local_customregistration'));
    }
}

// Setup page
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/customregistration/register.php'));
$PAGE->set_title('Register');
$PAGE->set_heading('Custom Registration');

$mform = new custom_registration_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/'));
} else if ($data = $mform->get_data()) {
    global $DB, $CFG;

    // Generate temporary password
    $temp_password = random_string(8);

    // Build user object without password
    $user = new stdClass();
    $user->username = $data->email;
    $user->email = $data->email;
    $user->firstname = $data->firstname;
    $user->lastname = $data->lastname;
    $user->country = $data->country;
    $user->phone1 = $data->phone;
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->policyagreed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;

    // Create the user first
    $user->id = user_create_user($user, false, false);

    if (!$user->id) {
        print_error('Failed to create user');
    }

    // ✅ Hash and store the password
    $hashedpassword = hash_internal_user_password($temp_password);
    $DB->set_field('user', 'password', $hashedpassword, ['id' => $user->id]);

    // ✅ Force password change
    set_user_preference('auth_forcepasswordchange', 1, $user->id); // ✅ Correct

    // ✅ Send the email
    $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    $supportuser = core_user::get_support_user();

    $subject = "Welcome to Moodle!";
    $message = "Hello {$user->firstname},\n\nYour account has been created.\n\nLogin email: {$user->email}\nTemporary password: {$temp_password}\n\nGo to {$CFG->wwwroot}/login/index.php to log in.";

    $result = email_to_user($user, $supportuser, $subject, $message);

    if ($result) {
        redirect(new moodle_url('/login/index.php'), 'Please log in using your email and temporary password.', 5);
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->notification('Failed to send email. Please contact support.', 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }
}

// Show the form
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
