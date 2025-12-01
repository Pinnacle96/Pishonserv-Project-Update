<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'includes/db_connect.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Get superadmin contact info for the public page if needed
$superadmin_info = $conn->query("SELECT name, phone, email FROM users WHERE role = 'superadmin' LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ | PishonServ</title>
    <meta name="description" content="Frequently Asked Questions about PishonServ's real estate, interior design, and solar services.">
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>public/images/favicon.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .brand-text { color: #092468; }
        .brand-gold { color: #CC9933; }
        .brand-bg { background-color: #092468; }
        .brand-bg-hover:hover { background-color: #0d307e; }
        .brand-border { border-color: #092468; }
        
        .faq-question {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: #f0f4f8;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .faq-question:hover {
            background-color: #e2eaf2;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            padding: 0 1rem;
            background-color: #fff;
            border-radius: 0 0 0.5rem 0.5rem;
            transition: max-height 0.3s ease-out, padding 0.3s ease-out;
        }

        .faq-answer.expanded {
            max-height: 500px; /* Adjust as needed */
            padding: 1rem;
            transition: max-height 0.5s ease-in, padding 0.5s ease-in;
        }

        .faq-icon {
            transform: rotate(0deg);
            transition: transform 0.3s ease;
        }

        .faq-icon.rotate {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-[#f5f7fa] text-brand-text min-h-screen">

<?php include 'includes/navbar.php'; ?>

<section class="container mx-auto pt-40 py-12 px-4 md:px-10 lg:px-16">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-extrabold brand-text">Frequently Asked Questions</h1>
        <p class="mt-4 text-xl text-gray-700 max-w-3xl mx-auto">
            Find quick answers to common questions about Pishonserv's services.
        </p>
    </div>

    <div class="max-w-4xl mx-auto space-y-4">
        <!-- FAQ Item 1 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="faq-question">
                <h3 class="text-lg font-semibold brand-text">1. Who is Pishonserv?</h3>
                <svg class="faq-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
            <div class="faq-answer">
                <p class="text-gray-700">Pishonserv is a trusted real estate company specializing in property sales, rentals, development, management, solar installation, and furniture services. Whether you’re looking to buy your dream home, invest in real estate, furnish your property, or install sustainable solar solutions, we are here to guide you every step of the way.</p>
            </div>
        </div>
        
        <!-- FAQ Item 2 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="faq-question">
                <h3 class="text-lg font-semibold brand-text">2. Where is Pishonserv located?</h3>
                <svg class="faq-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
            <div class="faq-answer">
                <p class="text-gray-700">Our office is located at Nomadian Tech Hub, 3rd Floor, 152 Obafemi Awolowo Way, opposite Airport Hotel near Allen Junction. However, we also offer virtual consultations for clients worldwide who prefer remote assistance.</p>
            </div>
        </div>

        <!-- FAQ Item 3 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="faq-question">
                <h3 class="text-lg font-semibold brand-text">3. What services does Pishonserv offer?</h3>
                <svg class="faq-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
            <div class="faq-answer">
                <p class="text-gray-700">Pishonserv offers the following services:</p>
                <ul class="list-disc list-inside mt-2 text-gray-700 space-y-1">
                    <li>Real Estate Services: Buying, renting, and short stays; secure access to apartments; a user-friendly platform connecting property owners and seekers; residential, commercial, and industrial listings.</li>
                    <li>Interior Design & Furniture Services: Tailored interior design, furniture sales and installation, and bespoke furnishing.</li>
                    <li>Solar Installation Services: Solar power system design and installation, renewable energy solutions, and maintenance advisory.</li>
                </ul>
            </div>
        </div>
        
        <!-- FAQ Item 4 -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="faq-question">
                <h3 class="text-lg font-semibold brand-text">4. How can I contact Pishonserv?</h3>
                <svg class="faq-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
            <div class="faq-answer">
                <p class="text-gray-700">You can reach us via:</p>
                <ul class="mt-2 text-gray-700 space-y-1">
                    <li><span class="font-bold">Phone:</span> +2348111973369</li>
                    <li><span class="font-bold">WhatsApp:</span> +2348137497811</li>
                    <li><span class="font-bold">Email:</span> inquiry@pishonserv.com</li>
                    <li><span class="font-bold">Website:</span> <a href="https://pishonserv.com/" class="text-blue-600 hover:underline">https://pishonserv.com/</a></li>
                    <li><span class="font-bold">Social Media:</span> Facebook, Instagram, Twitter, LinkedIn @Pishonserv</li>
                    <li><span class="font-bold">Calendar:</span> Book an appointment through our online calendar (available via our website).</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const faqQuestions = document.querySelectorAll('.faq-question');

        faqQuestions.forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const icon = question.querySelector('.faq-icon');

                // Toggle the expanded class on the answer
                const isExpanded = answer.classList.contains('expanded');
                if (isExpanded) {
                    answer.classList.remove('expanded');
                    icon.classList.remove('rotate');
                } else {
                    // Close other open answers
                    document.querySelectorAll('.faq-answer.expanded').forEach(openAnswer => {
                        openAnswer.classList.remove('expanded');
                    });
                    document.querySelectorAll('.faq-icon.rotate').forEach(openIcon => {
                        openIcon.classList.remove('rotate');
                    });

                    // Expand the current one
                    answer.classList.add('expanded');
                    icon.classList.add('rotate');
                }
            });
        });
    });
</script>

</body>
</html>
