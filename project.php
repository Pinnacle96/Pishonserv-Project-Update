<?php
session_start();
include 'includes/db_connect.php'; // Assuming this sets $base_path and handles DB connection
// navbar.php will be included after the <body> tag starts, as it outputs HTML
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pishonserv Solar Inverter Solutions</title>
    <meta name="description" content="Reliable Solar Power for Every Need">
    <link rel="icon" type="image/png" href="<?php echo $base_path; ?>public/images/favicon.png"> 
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Your existing brand colors from project.php
                        'brand-bg': '#f5f5f5',
                        'brand-navy': '#020b34',
                        'brand-gold': '#cb9833',
                        'brand-beige': '#ecdab7',
                        'brand-gray': '#32395a',
                        'navy-dark': '#020b34', // Duplicate, consider consolidating with brand-navy
                        'navy': '#32395a',     // Duplicate, consider consolidating with brand-gray
                        'gray-light': '#797d92',
                        'gray-lighter': '#a6a9b8',
                        'solar-gold': '#cb9833', // Duplicate, consider consolidating with brand-gold
                        
                        
                        // New colors inferred from about.php (adjust as needed for branding)
                        'pishonserv-blue': '#092468', // Primary blue from about.php
                        'pishonserv-orange': '#F4A124', // Primary orange from about.php
                        'pishonserv-orange-dark': '#d88b1c', // Hover state for orange
                    },
                    animation: {
                        'pulse-glow': 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        // Animations from about.php
                        'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
                        'scale-in': 'scaleIn 0.8s ease-out forwards',
                    }
                }
            }
        }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* Custom CSS from your original project.php, adapted to use Tailwind colors where possible */
        body {
            font-family: 'Inter', Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background: theme('colors.brand-bg'); /* Use Tailwind custom color */
            color: theme('colors.brand-navy'); /* Use Tailwind custom color */
        }
        
        /* Hero Section (from original project.php) */
        .hero-section {
            /* Keep original background-image, but adapt linear-gradient to Tailwind colors if possible */
            background-image: url('<?php echo $base_path; ?>assets/project/hero1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            position: relative;
        }
        .bg-gradient-custom { /* This class can be applied directly via Tailwind using 'bg-gradient-to-r from-brand-navy via-brand-gray to-brand-navy' */
            background: linear-gradient(135deg, theme('colors.brand-navy') 0%, theme('colors.brand-gray') 50%, theme('colors.brand-navy') 100%);
        }
        .hero-overlay {
            background: rgba(0, 0, 0, 0.1);
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        
        /* Custom logo shapes (from original project.php) - apply inline classes or Tailwind config */
        .logo-shape-1 {
            width: 32px;
            height: 24px;
            background-color: theme('colors.brand-gold'); /* Use Tailwind custom color */
            transform: rotate(12deg);
            border-radius: 3px;
        }
        .logo-shape-2 {
            width: 24px;
            height: 16px;
            background-color: theme('colors.brand-gold'); /* Use Tailwind custom color */
            transform: rotate(-12deg);
            border-radius: 3px;
            margin-top: -8px;
            margin-left: 8px;
        }
        
        .content-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh; /* Changed from 100% to 100vh for full viewport height */
            display: flex;
            flex-direction: column;
        }
        
        /* Main heading (from original project.php) - can be replaced with Tailwind */
        .main-heading {
            color: theme('colors.brand-navy'); /* Use Tailwind custom color */
            font-weight: 900;
            text-align: center;
            margin-bottom: 2rem;
            line-height: 1.1;
        }
        
        /* Subheading background (from original project.php) - can be replaced with Tailwind */
        .subheading-bg {
            background: rgba(59, 130, 246, 0.7); /* Using a hardcoded blue, consider mapping to Tailwind */
            backdrop-filter: blur(10px);
            border-radius: 9999px;
            padding: 1rem 2rem;
            margin-bottom: 3rem;
        }
        
        /* Responsive font sizes (from original project.php) - can be replaced with Tailwind utilities like text-4xl sm:text-5xl lg:text-6xl */
        @media (max-width: 768px) {
            .main-heading {
                font-size: 2.5rem;
            }
            .subheading-text {
                font-size: 1.25rem;
            }
        }
        @media (min-width: 768px) {
            .main-heading {
                font-size: 4rem;
            }
            .subheading-text {
                font-size: 1.5rem;
            }
        }
        @media (min-width: 1024px) {
            .main-heading {
                font-size: 5rem;
            }
            .subheading-text {
                font-size: 1.75rem;
            }
        }

        /* Animations from about.php, now defined via @keyframes and available through animate-* classes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Card Hover (from about.php) - keep if similar hover effects are desired */
        .team-card { /* Consider if this class name is appropriate here or needs a more generic name */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(9, 36, 104, 0.2); /* Using pishonserv-blue */
        }
        
        /* Navbar Spacing (from about.php) */
        .content-start {
            padding-top: 5rem; /* Applied to the main content container if necessary */
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="hero-section content-start">
        <div class="hero-overlay"></div>
        
        <div class="content-wrapper">
            <?php include 'includes/navbar.php'; ?>

            <main class="flex-1 flex flex-col justify-center items-center text-center px-4 md:px-8 pb-20 pt-16">
                <h1 class="text-3xl sm:text-5xl lg:text-7xl font-extrabold text-white mb-8 leading-tight 
                                animate-scale-in" style="animation-delay: 0.1s;">
                    Pishonserv Solar Inverter Solutions
                </h1>

                <div class="bg-blue-500 bg-opacity-70 backdrop-blur-md rounded-full px-8 py-4 mb-12 
                                animate-fade-in-up" style="animation-delay: 0.3s;">
                    <p class="text-white font-medium text-lg sm:text-xl lg:text-2xl m-0">
                        Reliable Solar Power for Every Need
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 mt-8">
                    <a href="https://calendly.com/pishonserv/30min" 
                       onclick="Calendly.initPopupWidget({url: 'https://calendly.com/pishonserv/30min'});return false;"
                       class="inline-block" style="animation-delay: 0.5s;">
                        <button class="bg-brand-gold text-white font-semibold px-8 py-3 rounded-full border-none cursor-pointer 
                                       text-lg transition-all duration-300 hover:bg-pishonserv-orange-dark hover:translate-y-[-3px] 
                                       hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-brand-gold focus:ring-opacity-75">
                            Get Free Quote
                        </button>
                    </a>
                    <a
                        href="#about"
                        class="bg-white bg-opacity-20 text-white font-semibold px-8 py-3 rounded-full border border-white border-opacity-30 cursor-pointer text-lg transition-all duration-300 hover:bg-opacity-30 hover:translate-y-[-3px] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-75 animate-fade-in-up"
                        style="animation-delay: 0.7s;"
                    >
                        Learn More
                    </a>
                </div>
            </main>
        </div>
    </div>

    
    <!--About-->
    <section class="bg-[#f5f5f5] py-16 px-8">
  <div class="max-w-6xl mx-auto" id="about">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-bold text-[#020b34] mb-2">ABOUT US</h2>
      <div class="w-24 h-1 bg-[#cb9833] mx-auto"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
      <div class="bg-white rounded-3xl p-8 border-2 border-black">
        <p class="text-[#020b34] text-sm leading-relaxed mb-6">
          Pishonserv Solar Inverter Solutions is a dedicated arm of Pishonserv,
          a diversified enterprise committed to transforming lives and
          communities through sustainable solutions. We are at the forefront of
          addressing Nigeria's energy challenges by providing innovative,
          reliable, and sustainable solar power solutions. Our unwavering
          commitment is to empower homes and businesses nationwide with
          consistent, affordable, and eco-friendly electricity generated from
          the sun.
        </p>
        <p class="text-[#020b34] text-sm leading-relaxed">
          At Pishonserv, we believe reliable power is the bedrock of progress.
          Through Pishonserv Solar Inverter Solutions, we bring you
          cutting-edge solar technology combined with unparalleled service,
          ensuring you experience the future of energy today.
        </p>
      </div>

      <div class="rounded-3xl overflow-hidden">
        <img
          src="assets/project/about1.jpg"
          alt="Solar panels installed on a red tile roof"
          class="w-full h-full object-cover"
        />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <div class="rounded-3xl overflow-hidden">
        <img
          src="assets/project/about2.png"
          alt="Solar power inverter device"
          class="w-full h-full object-cover"
        />
      </div>

      <div class="space-y-8">
        <div>
          <h3 class="text-2xl font-bold text-[#020b34] mb-4 text-right">
            Our Mission
          </h3>
          <div class="bg-white rounded-3xl p-6 border-2 border-black">
            <p class="text-[#020b34] text-sm leading-relaxed">
              To cultivate growth and prosperity by providing innovative,
              reliable, and sustainable solar inverter and power solutions that
              enhance lifestyles and support continuous operations for homes and
              businesses.
            </p>
          </div>
        </div>

        <div>
          <h3 class="text-2xl font-bold text-[#020b34] mb-4 text-right">
            Our Vision
          </h3>
          <div class="bg-white rounded-3xl p-6 border-2 border-black">
            <p class="text-[#020b34] text-sm leading-relaxed">
              To be a globally recognized leader in sustainable solar energy
              solutions, delivering value through innovation, reliability, and
              excellence, and transforming everything we touch into lasting
              success.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="bg-[#f5f5f5] py-16 px-4">
  <div class="max-w-7xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-4xl md:text-5xl font-bold text-[#020b34] mb-4">
        Our Core Values
      </h2>
      <div class="w-24 h-1 bg-[#cb9833] mx-auto mb-6"></div>
      <p class="text-[#32395a] text-lg max-w-4xl mx-auto leading-relaxed">
        At Pishonserv Solar Inverter Solutions, our operations are built upon
        the foundational values that define the entire Pishonserv brand
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-16">
      <div
        class="bg-[#ecdab7] rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300"
      >
        <div class="flex items-center gap-3 mb-4">
          <div class="bg-white p-2 rounded-full">
            <svg
              class="w-6 h-6 text-[#32395a]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 3l14 9-14 9V3z"
              />
            </svg>
          </div>
          <h3 class="font-semibold text-[#020b34] text-lg">Sustainability</h3>
        </div>
        <p class="text-[#32395a] text-sm leading-relaxed">
          We are deeply committed to eco-friendly practices in our solar energy
          solutions, actively contributing to a greener environment and
          protecting the planet for future generations.
        </p>
      </div>

      <div
        class="bg-[#ecdab7] rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300"
      >
        <div class="flex items-center gap-3 mb-4">
          <div class="bg-white p-2 rounded-full">
            <svg
              class="w-6 h-6 text-[#32395a]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
              />
            </svg>
          </div>
          <h3 class="font-semibold text-[#020b34] text-lg">Innovation</h3>
        </div>
        <p class="text-[#32395a] text-sm leading-relaxed">
          We embrace technology and creativity, continuously seeking and
          delivering cutting-edge solar inverter solutions that not only meet
          but exceed contemporary energy demands.
        </p>
      </div>

      <div
        class="bg-[#ecdab7] rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300"
      >
        <div class="flex items-center gap-3 mb-4">
          <div class="bg-white p-2 rounded-full">
            <svg
              class="w-6 h-6 text-[#32395a]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
          </div>
          <h3 class="font-semibold text-[#020b34] text-lg">Excellence</h3>
        </div>
        <p class="text-[#32395a] text-sm leading-relaxed">
          We strive for superior quality in all our products and services. From
          the initial consultation to post-installation support, your
          satisfaction is our paramount concern.
        </p>
      </div>

      <div
        class="bg-[#ecdab7] rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300"
      >
        <div class="flex items-center gap-3 mb-4">
          <div class="bg-white p-2 rounded-full">
            <svg
              class="w-6 h-6 text-[#32395a]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
              />
            </svg>
          </div>
          <h3 class="font-semibold text-[#020b34] text-lg">
            Community Impact
          </h3>
        </div>
        <p class="text-[#32395a] text-sm leading-relaxed">
          We are dedicated to improving the lives of the communities we serve.
          By promoting access to reliable and affordable solar energy, we foster
          economic empowerment and enhance quality of life nationwide.
        </p>
      </div>

      <div
        class="bg-[#ecdab7] rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300"
      >
        <div class="flex items-center gap-3 mb-4">
          <div class="bg-white p-2 rounded-full">
            <svg
              class="w-6 h-6 text-[#32395a]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"
              />
            </svg>
          </div>
          <h3 class="font-semibold text-[#020b34] text-lg">Integrity</h3>
        </div>
        <p class="text-[#32395a] text-sm leading-relaxed">
          We operate with unwavering transparency, honesty, and accountability
          in all aspects of our business, ensuring you receive trustworthy
          advice and service.
        </p>
      </div>
    </div>

    <div class="relative w-full h-96 md:h-[500px] rounded-2xl overflow-hidden bg-gray-200">
      <img
        src="assets/project/core value.png"
        alt="Solar energy professionals reviewing plans with solar panel installation in background"
        class="w-full h-full object-cover"
        loading="lazy"
      />
    </div>
  </div>
</section>
<section
  class="bg-gradient-to-r from-gray-200 via-amber-100 to-amber-300 py-12 px-6"
>
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <div class="space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="rounded-lg overflow-hidden shadow-lg">
            <img
              src="assets/project/why1.jpeg"
              alt="Solar panel installation workers"
              class="w-full h-64 object-cover"
            />
          </div>
          <div class="rounded-lg overflow-hidden shadow-lg">
            <img
              src="assets/project/why2.png"
              alt="House with solar panels and inverter"
              class="w-full h-64 object-cover"
            />
          </div>
        </div>

        <div class="space-y-6">
          <h2 class="text-3xl lg:text-4xl font-bold text-amber-600 leading-tight">
            Benefits of Pishonserv Solar Inverter Solutions
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div
              class="bg-amber-400 text-gray-900 px-4 py-2 rounded-full text-center font-medium text-sm shadow-md"
            >
              Uninterrupted Power Supply
            </div>
            <div
              class="bg-amber-400 text-gray-900 px-4 py-2 rounded-full text-center font-medium text-sm shadow-md"
            >
              Quiet & Clean Operation
            </div>
            <div
              class="bg-amber-400 text-gray-900 px-4 py-2 rounded-full text-center font-medium text-sm shadow-md"
            >
              Appliance Protection
            </div>
            <div
              class="bg-amber-400 text-gray-900 px-4 py-2 rounded-full text-center font-medium text-sm shadow-md"
            >
              Eco-Friendly Energy
            </div>
            <div
              class="bg-amber-400 text-gray-900 px-4 py-2 rounded-full text-center font-medium text-sm shadow-md"
            >
              Significant Cost Savings
            </div>
            <div
              class="bg-amber-400 text-gray-900 px-4 py-2 rounded-full text-center font-medium text-sm shadow-md"
            >
              Enhanced Comfort & Productivity
            </div>
          </div>
        </div>
      </div>

      <div
        class="bg-gradient-to-br from-amber-200 to-amber-400 rounded-2xl p-8 shadow-xl h-fit"
      >
        <h3 class="text-3xl lg:text-4xl font-bold text-white mb-6 text-center">
          Why Choose Solar Inverter?
        </h3>

        <p class="text-white text-center mb-8 leading-relaxed text-sm">
          In today's dynamic environment, consistent power is not a luxury, but
          a necessity. Pishonserv Solar Inverter Solutions offers a superior
          alternative to traditional energy sources, providing you with a
          reliable, efficient, and cost-effective power supply harnessed from
          the sun.
        </p>

        <div class="space-y-4">
          <div class="bg-white bg-opacity-90 rounded-xl p-5 shadow-lg">
            <h4 class="text-lg font-bold text-gray-800 mb-3 text-center">
              Unrivaled Availability
            </h4>
            <p class="text-gray-700 text-xs leading-relaxed text-justify">
              Due to the inconsistent public power supply in Nigeria, many homes
              and businesses have wisely chosen solar and inverter systems as the
              most reliable alternative for continuous power. Say goodbye to
              blackouts and enjoy uninterrupted electricity, day and night.
            </p>
          </div>

          <div class="bg-white bg-opacity-90 rounded-xl p-5 shadow-lg">
            <h4 class="text-lg font-bold text-gray-800 mb-3 text-center">
              Economical in the Long Run
            </h4>
            <p class="text-gray-700 text-xs leading-relaxed text-justify">
              While public power supplies can be costly (e.g., homes in "Band A"
              often pay an average of ₦50,000 every 3 days), opting for a solar
              solution is a smarter, more economical long-term investment. With a
              one-off setup, you can power your home or business with free energy
              from the sun, leading to significant savings over the years.
              Generally speaking, it is cheaper in the long run using solar
              infrastructure to generate your Energy over the years.
            </p>
          </div>

          <div class="bg-white bg-opacity-90 rounded-xl p-5 shadow-lg">
            <h4 class="text-lg font-bold text-gray-800 mb-3 text-center">
              Stable & Reliable Voltage
            </h4>
            <p class="text-gray-700 text-xs leading-relaxed text-justify">
              Unlike public supply, which can suffer from unpredictable voltage
              fluctuations (high or low) that risk damaging your valuable
              appliances, solar power systems provide a stable and reliable
              voltage. This ensures the longevity and safety of your electronics.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


    <section class="bg-[#f5f5f5] py-16 px-8">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-bold text-[#020b34] mb-2">
        Why Choose Pishonserv?
      </h2>
      <div class="w-24 h-1 bg-[#cb9833] mx-auto"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      <div
        class="bg-white rounded-3xl p-8 border-2 border-black flex flex-col items-center text-center shadow-lg"
      >
        <img
          src="assets/project/Asset 2.svg"
          alt="Expertise icon"
          class="mb-4 w-20 h-20 object-contain"
        />
        <h3 class="text-xl font-bold text-[#020b34] mb-3">
          Unrivaled Expertise
        </h3>
        <p class="text-[#020b34] text-sm leading-relaxed">
          Our team comprises highly skilled and certified professionals with
          extensive experience in solar energy solutions. We understand the
          intricacies of solar inverter systems and provide tailored solutions
          that perfectly match your needs.
        </p>
      </div>

      <div
        class="bg-white rounded-3xl p-8 border-2 border-black flex flex-col items-center text-center shadow-lg"
      >
        <img
          src="assets/project/Asset 8.svg"
          alt="Quality icon"
          class="mb-4 w-20 h-20 object-contain"
        />
        <h3 class="text-xl font-bold text-[#020b34] mb-3">
          Premium Quality Products
        </h3>
        <p class="text-[#020b34] text-sm leading-relaxed">
          We partner with leading global manufacturers to supply only the
          highest quality solar panels, inverters, and batteries. Our products
          are rigorously tested for durability, efficiency, and performance,
          ensuring you get the best value for your investment.
        </p>
      </div>

      <div
        class="bg-white rounded-3xl p-8 border-2 border-black flex flex-col items-center text-center shadow-lg"
      >
        <img
          src="assets/project/community.svg"
          alt="Customer Satisfaction icon"
          class="mb-4 w-20 h-20 object-contain"
        />
        <h3 class="text-xl font-bold text-[#020b34] mb-3">
          Unwavering Customer Satisfaction
        </h3>
        <p class="text-[#020b34] text-sm leading-relaxed">
          Your satisfaction is our priority. From the initial consultation and
          system design to installation and after-sales support, we are
          committed to providing a seamless and hassle-free experience. Our
          dedicated support team is always ready to assist you.
        </p>
      </div>

      <div
        class="bg-white rounded-3xl p-8 border-2 border-black flex flex-col items-center text-center shadow-lg"
      >
        <img
          src="assets/project/excellenc.svg"
          alt="Tailored Solutions icon"
          class="mb-4 w-20 h-20 object-contain"
        />
        <h3 class="text-xl font-bold text-[#020b34] mb-3">
          Tailored Solutions
        </h3>
        <p class="text-[#020b34] text-sm leading-relaxed">
          We understand that every energy need is unique. That's why we offer
          customized solar inverter solutions designed to meet your specific
          power requirements, budget, and lifestyle, ensuring optimal
          efficiency and cost-effectiveness.
        </p>
      </div>

      <div
        class="bg-white rounded-3xl p-8 border-2 border-black flex flex-col items-center text-center shadow-lg"
      >
        <img
          src="assets/project/integrity .svg"
          alt="Support icon"
          class="mb-4 w-20 h-20 object-contain"
        />
        <h3 class="text-xl font-bold text-[#020b34] mb-3">
          Comprehensive After-Sales Support
        </h3>
        <p class="text-[#020b34] text-sm leading-relaxed">
          Our commitment to you extends beyond installation. We provide robust
          after-sales support, including maintenance services and technical
          assistance, to ensure your solar inverter system operates flawlessly
          for years to come.
        </p>
      </div>

      <div
        class="bg-white rounded-3xl p-8 border-2 border-black flex flex-col items-center text-center shadow-lg"
      >
        <img
          src="assets/project/inovation.svg"
          alt="Track Record icon"
          class="mb-4 w-20 h-20 object-contain"
        />
        <h3 class="text-xl font-bold text-[#020b34] mb-3">
          Proven Track Record
        </h3>
        <p class="text-[#020b34] text-sm leading-relaxed">
          With years of experience and countless successful installations across
          Nigeria, Pishonserv Solar Inverter Solutions has a verifiable track
          record of delivering reliable and efficient solar power solutions.
          Join our growing family of satisfied customers.
        </p>
      </div>
    </div>
  </div>
</section>

<section
  class="bg-gradient-to-r from-[#020b34] to-[#32395a] py-16 px-8 text-white"
>
  <div class="max-w-4xl mx-auto text-center">
    <h2 class="text-4xl font-bold mb-6 leading-tight">
      Ready to Embrace Sustainable Energy?
    </h2>
    <p class="text-lg mb-8">
      Contact Pishonserv Solar Inverter Solutions today for a free
      consultation and discover how we can empower your home or business with
      clean, reliable, and affordable solar power.
    </p>
        <a
      href="https://calendly.com/pishonserv/30min"
      onclick="Calendly.initPopupWidget({url: 'https://calendly.com/pishonserv/30min'});return false;"
      class="inline-block bg-[#cb9833] text-[#020b34] font-bold py-4 px-10 rounded-full text-lg hover:bg-amber-500 transition-colors duration-300 shadow-lg"
    >
      Get a Free Quote
    </a>
      </div>
</section>

<section class="bg-white">
  <div class="relative h-[600px]">
    <img
      src="assets/project/our product 1.png"
      alt="Professional team working on solar solutions"
      class="w-full h-full object-cover"
    />
    <div
      class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/30"
    >
      <div class="container mx-auto px-8 h-full flex items-center">
        <div class="max-w-xl">
          <h1 class="text-5xl font-bold text-white mb-8 leading-tight">
            Our Products &<br />
            Capacities
          </h1>
          <div
            class="bg-black/20 backdrop-blur-sm rounded-2xl p-8 border border-white/10"
          >
            <p class="text-white text-base leading-relaxed font-light">
              Pishonserv Solar Inverter Solutions offers a comprehensive range
              of capacities to meet diverse energy needs, from essential home
              lighting to robust business operations, all integrated with
              efficient solar technology. Our expert team is also available to
              perform a free energy assessment and load calculation to help you
              determine the ideal solar inverter system for your specific
              requirements.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white py-20">
    <div class="container mx-auto px-8">
      <div class="grid lg:grid-cols-2 gap-16 items-start">
        <div class="pt-8">
          <h2 class="text-5xl font-bold text-[#020b34] mb-16 leading-tight">
            Our Comprehensive<br />
            Services
          </h2>

          <div class="space-y-10">
            <div class="flex items-center gap-6">
              <div class="bg-[#020b34] rounded-full p-4 flex-shrink-0">
                <svg
                  class="w-7 h-7 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m0 0h8m-8 0a2 2 0 100 4 2 2 0 000-4zm8 0a2 2 0 100 4 2 2 0 000-4z"
                  ></path>
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-medium text-[#020b34] leading-relaxed">
                  Sales of Premium Solar Inverters<br />
                  and Batteries
                </h3>
              </div>
            </div>

            <div class="flex items-center gap-6">
              <div class="bg-[#020b34] rounded-full p-4 flex-shrink-0">
                <svg
                  class="w-7 h-7 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                  ></path>
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  ></path>
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-medium text-[#020b34] leading-relaxed">
                  Professional Solar System<br />
                  Installation
                </h3>
              </div>
            </div>

            <div class="flex items-center gap-6">
              <div class="bg-[#020b34] rounded-full p-4 flex-shrink-0">
                <svg
                  class="w-7 h-7 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a1 1 0 01-1-1V9a1 1 0 011-1h1a2 2 0 100-4H4a1 1 0 01-1-1V4a1 1 0 011-1h3a1 1 0 011 1v1z"
                  ></path>
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-medium text-[#020b34] leading-relaxed">
                  Expert Load Calculation and<br />
                  Energy Assessment
                </h3>
              </div>
            </div>

            <div class="flex items-center gap-6">
              <div class="bg-[#020b34] rounded-full p-4 flex-shrink-0">
                <svg
                  class="w-7 h-7 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  ></path>
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-medium text-[#020b34] leading-relaxed">
                  Reliable Maintenance<br />
                  and Repair
                </h3>
              </div>
            </div>
          </div>
        </div>

        <div class="relative">
          <img
            src="assets/project/our product 2.png"
            alt="Two PISHONSERV technicians in yellow hard hats installing solar equipment"
            class="w-full h-[600px] object-cover rounded-lg"
          />
        </div>
      </div>
    </div>
  </div>
</section>






<section class="min-h-screen relative overflow-hidden bg-gradient-to-br from-blue-900 via-purple-900 to-black">
  <div class="absolute inset-0 overflow-hidden">
    <div
      class="absolute top-10 left-10 w-32 h-32 rounded-full border-2 border-white/10"
    ></div>
    <div
      class="absolute top-20 right-20 w-24 h-24 rounded-full border border-white/10"
    ></div>
    <div
      class="absolute bottom-20 left-20 w-40 h-40 rounded-full border border-white/10"
    ></div>
    <div
      class="absolute bottom-10 right-10 w-28 h-28 rounded-full border-2 border-white/10"
    ></div>
    <div
      class="absolute top-1/2 left-1/4 w-20 h-20 rounded-full border border-white/10"
    ></div>
    <div
      class="absolute top-1/3 right-1/3 w-36 h-36 rounded-full border border-white/10"
    ></div>
  </div>

  <div class="relative z-10 p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
      <header class="mb-8 text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
          Solar Inverter Comparison
        </h1>
        <p class="text-white/80 text-lg">
          Compare specifications and pricing for different inverter capacities
        </p>
      </header>

      <section
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
      >
        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">1KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">15</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">3</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">-</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">-</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">-</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦748,000</span>-->
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                <span class="text-white font-bold">N/A</span>
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">1.5KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">22</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">5</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">2</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">-</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">-</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">-</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦1,100,000</span>-->
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                <span class="text-white font-bold">N/A</span>
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">2KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">22</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">5</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">2</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">-</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">-</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦1,350,000</span>-->
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                <span class="text-white font-bold">N/A</span>
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">2.5KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">25</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">8</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">4</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">4</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">-</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">-</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦2,298,000</span>-->
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦1,750,000</span>-->
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">3.5KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">30</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">10</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">4</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">4</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">-</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦2,298,000</span>-->
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦5,680,000</span>-->
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">5KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">40</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">10</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">5</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">5</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">1</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">2</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">1/2 OR 2/2</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                <span class="text-white font-bold">N/A</span>
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦5,680,000</span>-->
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">10KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">55</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">15</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">10</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">10</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">2</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">4</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">3</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                <span class="text-white font-bold">N/A</span>
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦11,030,000</span>-->
              </div>
            </div>
          </footer>
        </article>

        <article
          class="bg-black/20 border border-white/30 rounded-lg p-6 hover:bg-black/30 transition-colors"
        >
          <header class="mb-4">
            <h2 class="text-2xl font-bold text-white text-center">15KVA</h2>
          </header>
          <section class="space-y-3">
            <div class="flex justify-between">
              <span class="text-white/80">Max. Bulbs:</span>
              <span class="text-white font-medium">68</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fans:</span>
              <span class="text-white font-medium">25</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. TVs:</span>
              <span class="text-white font-medium">15</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Laptops:</span>
              <span class="text-white font-medium">15</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Fridges:</span>
              <span class="text-white font-medium">4</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. Freezers:</span>
              <span class="text-white font-medium">8</span>
            </div>
            <div class="flex justify-between">
              <span class="text-white/80">Max. A/C (HP):</span>
              <span class="text-white font-medium">5</span>
            </div>
          </section>
          <footer class="mt-6 pt-4 border-t border-white/20">
            <div class="space-y-2">
              <div class="flex justify-between">
                <span class="text-white/80">Tubular:</span>
                <span class="text-white font-bold">N/A</span>
              </div>
              <div class="flex justify-between">
                <span class="text-white/80">Lithium:</span>
                 <span class="text-white font-bold">N/A</span>
                <!--<span class="text-white font-bold">₦15,700,000</span>-->
              </div>
            </div>
          </footer>
        </article>
      </section>

      <section class="mt-12 bg-black/20 border border-white/30 rounded-lg p-6">
        <header class="mb-4">
          <h2 class="text-2xl font-bold text-white text-center">
            Inverter Comparison Summary
          </h2>
        </header>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
          <div>
            <h3 class="text-lg font-semibold text-white mb-2">Small Scale</h3>
            <p class="text-white/80 text-sm">
              1KVA - 2KVA suitable for basic lighting and small appliances
            </p>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-white mb-2">Medium Scale</h3>
            <p class="text-white/80 text-sm">
              2.5KVA - 5KVA ideal for homes with refrigeration needs
            </p>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-white mb-2">Large Scale</h3>
            <p class="text-white/80 text-sm">
              10KVA - 15KVA perfect for commercial or large residential use
            </p>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<section
  class="min-h-screen bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 relative overflow-hidden"
>
  <div class="absolute inset-0">
    <div
      class="absolute top-20 right-20 w-32 h-24 bg-slate-700 transform rotate-12 opacity-80"
    >
      <div class="w-full h-16 bg-slate-600 relative">
        <div class="absolute top-2 left-2 w-4 h-4 bg-orange-400 rounded-sm"></div>
        <div class="absolute top-2 right-2 w-4 h-4 bg-orange-400 rounded-sm"></div>
        <div class="absolute bottom-2 left-2 w-4 h-6 bg-orange-400 rounded-sm"></div>
      </div>
      <div
        class="w-0 h-0 border-l-16 border-r-16 border-b-8 border-l-transparent border-r-transparent border-b-slate-600 mx-auto"
      ></div>
    </div>

    <div
      class="absolute left-10 top-32 w-48 h-32 bg-slate-700 transform -rotate-12 shadow-2xl"
    >
      <div class="grid grid-cols-6 grid-rows-4 h-full p-2 gap-1">
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
      </div>
      <div
        class="absolute -bottom-4 left-1/2 transform -translate-x-1/2 w-32 h-8 bg-orange-500 opacity-30 blur-xl rounded-full animate-pulse-glow"
      ></div>
    </div>

    <div
      class="absolute right-10 top-40 w-48 h-32 bg-slate-700 transform rotate-12 shadow-2xl"
    >
      <div class="grid grid-cols-6 grid-rows-4 h-full p-2 gap-1">
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
        <div class="bg-slate-600 border border-slate-500"></div>
      </div>
      <div
        class="absolute -bottom-4 left-1/2 transform -translate-x-1/2 w-32 h-8 bg-orange-500 opacity-30 blur-xl rounded-full animate-pulse-glow"
      ></div>
    </div>
  </div>

  <div
    class="relative z-10 flex flex-col items-center justify-center min-h-screen px-8"
  >
    <div class="relative mb-8">
      <div
        class="w-64 h-80 bg-gradient-to-b from-gray-100 to-gray-200 rounded-3xl shadow-2xl relative"
      >
        <div
          class="w-full h-24 bg-gradient-to-b from-gray-50 to-gray-100 rounded-t-3xl flex items-center justify-center"
        >
          <div
            class="w-16 h-16 bg-slate-800 rounded-2xl flex items-center justify-center"
          >
            <div
              class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center"
            >
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path
                  d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.477.859h4z"
                />
              </svg>
            </div>
          </div>
        </div>

        <div class="px-8 py-6 space-y-4">
          <div class="w-full h-3 bg-slate-400 rounded-full"></div>
          <div class="w-full h-3 bg-slate-400 rounded-full"></div>
          <div class="w-full h-3 bg-slate-400 rounded-full"></div>
        </div>

        <div
          class="absolute bottom-0 w-full h-32 bg-gradient-to-t from-gray-200 to-gray-100 rounded-b-3xl"
        ></div>
      </div>

      <div
        class="absolute -left-20 top-1/2 w-20 h-1 bg-gradient-to-r from-orange-500 to-orange-400 shadow-lg shadow-orange-500/50 rounded-full animate-pulse-glow"
      >
        <div class="absolute inset-0 bg-orange-400 blur-sm rounded-full"></div>
      </div>
      <div
        class="absolute -right-20 top-1/2 w-20 h-1 bg-gradient-to-l from-orange-500 to-orange-400 shadow-lg shadow-orange-500/50 rounded-full animate-pulse-glow"
      >
        <div class="absolute inset-0 bg-orange-400 blur-sm rounded-full"></div>
      </div>

      <div class="absolute -bottom-16 left-1/2 transform -translate-x-1/2">
        <svg width="200" height="80" viewBox="0 0 200 80" class="overflow-visible">
          <defs>
            <filter id="glow">
              <feGaussianBlur stdDeviation="3" result="coloredBlur" />
              <feMerge>
                <feMergeNode in="coloredBlur" />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
          </defs>
          <path
            d="M 20 40 Q 100 80 180 40"
            stroke="#f97316"
            stroke-width="4"
            fill="none"
            filter="url(#glow)"
            class="drop-shadow-lg animate-pulse-glow"
          />
        </svg>
      </div>
    </div>

    <div class="text-center mb-8 relative">
      <div
        class="bg-white/10 backdrop-blur-md rounded-2xl px-8 py-6 border border-white/20 shadow-2xl"
      >
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
          What Makes Pishonserv Different?
        </h1>
      </div>
    </div>

    <div class="text-center max-w-4xl relative">
      <div
        class="bg-white/10 backdrop-blur-md rounded-2xl px-8 py-6 border border-white/20 shadow-2xl"
      >
        <p class="text-lg md:text-xl text-gray-200 leading-relaxed">
          Choosing Pishonserv Solar Inverter Solutions means opting for a partner
          that stands for
          <span class="text-orange-400 font-semibold">excellence</span>,
          <span class="text-orange-400 font-semibold">reliability</span>, and
          <span class="text-orange-400 font-semibold">customer satisfaction</span>
          in renewable energy.
        </p>
      </div>
    </div>
  </div>

  <div
    class="absolute top-1/4 left-1/4 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl animate-pulse-glow"
  ></div>
  <div
    class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl animate-pulse-glow"
  ></div>
</section>

<section class="bg-[#020b34] py-16 px-6">
  <div class="max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <div class="bg-[#2a3154] rounded-3xl p-8 space-y-6">
        <div>
          <h2 class="text-[#cb9833] text-lg font-bold mb-3">
            Holistic Solar Power Solutions:
          </h2>
          <p class="text-white text-sm leading-relaxed">
            We offer integrated systems, guiding you from initial consultation
            and sales to professional installation and ongoing maintenance of
            your solar setup.
          </p>
        </div>

        <div>
          <h2 class="text-[#cb9833] text-lg font-bold mb-3">
            Rapid & Efficient Deployment:
          </h2>
          <p class="text-white text-sm leading-relaxed">
            Experience quick turnaround times from order placement to final
            installation, ensuring you get the solar power you need, precisely
            when you need it.
          </p>
        </div>

        <div>
          <h2 class="text-[#cb9833] text-lg font-bold mb-3">
            Superior Battery Technology:
          </h2>
          <p class="text-white text-sm leading-relaxed">
            We feature advanced, certified lithium batteries with extended
            lifespans and unparalleled performance, designed to integrate
            perfectly with solar charging.
          </p>
        </div>

        <div>
          <h2 class="text-[#cb9833] text-lg font-bold mb-3">
            Sustainable & Serene Energy:
          </h2>
          <p class="text-white text-sm leading-relaxed">
            Enjoy clean, silent, and environmentally friendly power,
            contributing to a healthier planet while enhancing your comfort
            through solar energy.
          </p>
        </div>
      </div>

      <div
        class="bg-[#1a4a3a] rounded-3xl p-6 flex flex-col items-center justify-center min-h-[600px]"
      >
        <div class="w-full max-w-sm">
          <img
            src="assets/project/holistic1.jpg"
            alt="Kartel Solar Inverter and Battery System"
            class="w-full h-auto object-contain"
          />
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-[#e8e8e8] rounded-3xl p-3 overflow-hidden min-h-[280px]">
          <div
            class="w-full h-full bg-white rounded-2xl flex items-center justify-center"
          >
            <img
              src="assets/project/hoslistic 2.jpeg"
              alt="Solar Installation Workers"
              class="w-full h-full object-cover rounded-2xl"
            />
          </div>
        </div>

        <div class="bg-[#e8e8e8] rounded-3xl p-3 overflow-hidden min-h-[280px]">
          <div
            class="w-full h-full bg-white rounded-2xl flex items-center justify-center"
          >
            <img
              src="assets/project/holistic 3.jpeg"
              alt="Solar System Maintenance"
              class="w-full h-full object-cover rounded-2xl"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</section>



<section class="relative min-h-screen w-full overflow-hidden flex flex-col">
  <div class="absolute inset-0">
    <img
      class="w-full h-full object-cover"
      src="assets/project/last.png"
      alt="Modern living room with solar system"
    />
    <div class="absolute inset-0 bg-black/30"></div>
  </div>

  <div class="relative z-10 flex flex-1 flex-col justify-between py-8 md:py-16">
    <div class="flex flex-col md:flex-row justify-end items-start px-4 sm:px-6 lg:px-8">
      <div class="hidden md:block md:w-1/2 lg:w-3/5"></div> 
      
      <div
        class="w-full md:w-1/2 lg:w-2/5 xl:w-[465px] bg-gradient-to-br from-stone-500/5 to-stone-300/5 rounded-[30px] backdrop-blur-lg border-b-4 border-zinc-300 p-6 md:p-10 space-y-6"
      >
        <h2
          class="text-right text-neutral-100 text-3xl md:text-4xl font-extrabold font-['Poppins'] mb-8"
        >
          FAQ
        </h2>

        <div class="space-y-8">
          <div class="text-right">
            <h3
              class="text-yellow-600 text-lg md:text-xl font-bold font-['Poppins'] leading-tight mb-2"
            >
              Can inverter power my entire House?
            </h3>
            <p
              class="text-neutral-100 text-sm md:text-base font-normal font-['Poppins'] leading-relaxed"
            >
              Yes, it depends on the capacity.
            </p>
          </div>

          <div class="text-right">
            <h3
              class="text-yellow-600 text-lg md:text-xl font-bold font-['Poppins'] leading-tight mb-2"
            >
              Can I use inverter without solar?
            </h3>
            <p
              class="text-neutral-100 text-sm md:text-base font-normal font-['Poppins'] leading-relaxed"
            >
              Yes, inverter can be used without solar
            </p>
          </div>

          <div class="text-right">
            <h3
              class="text-yellow-600 text-lg md:text-xl font-bold font-['Poppins'] leading-tight mb-2"
            >
              Can my freezer run on inverter 24/7?
            </h3>
            <p
              class="text-neutral-100 text-sm md:text-base font-normal font-['Poppins'] leading-relaxed"
            >
              Yes, with good back up system (battery)
            </p>
          </div>

          <div class="text-right">
            <h3
              class="text-yellow-600 text-lg md:text-xl font-bold font-['Poppins'] leading-tight mb-2"
            >
              Can my inverter system supply the house even when the public
              supply is off and the inverter is on?
            </h3>
            <p
              class="text-neutral-100 text-sm md:text-base font-normal font-['Poppins'] leading-relaxed"
            >
              Yes, if the inverter is in on position
            </p>
          </div>

          <div class="text-right">
            <h3
              class="text-yellow-600 text-lg md:text-xl font-bold font-['Poppins'] leading-tight mb-2"
            >
              What is the difference between inverter and solar?
            </h3>
            <p
              class="text-neutral-100 text-sm md:text-base font-normal font-['Poppins'] leading-relaxed"
            >
              Inverter produces light or electricity from the batteries while
              solar charges the battery using sun
            </p>
          </div>

          <div class="text-right">
            <h3
              class="text-yellow-600 text-lg md:text-xl font-bold font-['Poppins'] leading-tight mb-2"
            >
              Can I go off grid?
            </h3>
            <p
              class="text-neutral-100 text-sm md:text-base font-normal font-['Poppins'] leading-relaxed"
            >
              Yes, if you have a large solar solution
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-auto pt-8 px-4 sm:px-6 lg:px-8">
      <div
        class="text-center text-neutral-100 text-base md:text-xl font-extrabold font-['Poppins']"
      >
        Empowering Nigeria with Sustainable & Uninterrupted Solar Power.<br />Pishonserv:
        Transforming Lives, Cultivate Growth. Where God Rules.
      </div>
    </div>
  </div>
</section>

<script>
  // Minimal JavaScript for enhanced interactions
  document.addEventListener("DOMContentLoaded", function () {
    // Add smooth scroll behavior for any internal links
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener("click", function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute("href"));
        if (target) {
          target.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      });
    });

    // Optional: Add intersection observer for animations for .bg-brand-beige (if used elsewhere)
    const cardsBrandBeige = document.querySelectorAll(".bg-brand-beige");
    const observerBrandBeige = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translateY(0)";
          }
        });
      },
      { threshold: 0.1 }
    );

    cardsBrandBeige.forEach((card) => {
      card.style.opacity = "0";
      card.style.transform = "translateY(20px)";
      card.style.transition = "opacity 0.6s ease, transform 0.6s ease";
      observerBrandBeige.observe(card);
    });

    // Minimal JavaScript for card animations for .bg-[#ecdab7] (if used elsewhere)
    const cardsEcdab7 = document.querySelectorAll(".bg-\\[\\#ecdab7\\]");

    const observerEcdab7 = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry, index) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              entry.target.style.opacity = "1";
              entry.target.style.transform = "translateY(0)";
            }, index * 100);
          }
        });
      },
      { threshold: 0.1 }
    );

    cardsEcdab7.forEach((card) => {
      card.style.opacity = "0";
      card.style.transform = "translateY(20px)";
      card.style.transition = "opacity 0.6s ease, transform 0.6s ease";
      observerEcdab7.observe(card);
    });

    // Simple fade-in animation for 'main' element
    const mainElement = document.querySelector("main");
    if (mainElement) {
      mainElement.style.opacity = "0";
      mainElement.style.transform = "translateY(30px)";
      mainElement.style.transition = "opacity 1s ease-out, transform 1s ease-out";

      setTimeout(() => {
        mainElement.style.opacity = "1";
        mainElement.style.transform = "translateY(0)";
      }, 500);
    }

    // Button click effects
    const buttons = document.querySelectorAll("button");
    buttons.forEach((button) => {
      button.addEventListener("click", function () {
        this.style.transform = "scale(0.95)";
        setTimeout(() => {
          this.style.transform = "scale(1)";
        }, 150);
      });
    });
  });
</script>


<link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
    <script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>


    <?php include 'includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Only load Zoho SalesIQ if it's explicitly needed on this page, otherwise remove.
        // Keeping it for consistency with about.php inference.
        if (!window.$zoho || !window.$zoho.salesiq) {
            console.warn('Zoho SalesIQ not initialized. Loading fallback...');
            window.$zoho = window.$zoho || {};
            window.$zoho.salesiq = window.$zoho.salesiq || {
                ready: function() {}
            };
            var zohoScript = document.createElement('script');
            zohoScript.id = 'zsiqscript';
            zohoScript.src =
                'https://salesiq.zohopublic.com/widget?wc=siqbf4b21531e2ec082c78d765292863df4a9787c4f0ba205509de7585b7a8d3e78';
            zohoScript.async = true;
            document.body.appendChild(zohoScript);
        }

        // Timeout to check if Zoho loaded
        setTimeout(function() {
            if (!document.querySelector('.zsiq_floatmain')) {
                console.error('Zoho SalesIQ widget failed to load on Project page.');
            }
        }, 5000);
    });
    </script>
</body>
</html>