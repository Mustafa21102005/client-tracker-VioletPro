<?php

namespace Database\Seeders;

use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    private const CLIENTS = [
        'Abdullah Al-Harbi',
        'Fahad Al-Otaibi',
        'Sara Al-Qahtani',
        'Nora Al-Dosari',
        'Khalid Al-Zahrani',
        'Reem Al-Shehri',
        'Mohammed Al-Ghamdi',
        'Lama Al-Mutairi',
        'Faisal Al-Subaie',
        'Hessa Al-Anazi',
        'Turki Al-Shammari',
        'Maha Al-Rashid',
        'Omar Haddad',
        'Layla Mansour',
        'Daniel Foster',
        'Priya Nair',
        'Yusuf Demir',
        'Sophie Laurent',
    ];

    private const COMPANIES = [
        'Al Noor Trading Co.',
        'Najd Digital Solutions',
        'Falcon Real Estate',
        'Oasis Café Group',
        'Tamkeen Consulting',
        'Desert Rose Boutique',
        'Blue Wave Logistics',
        'Hajar Medical Center',
        'Nova Fitness Club',
        'Sahara Auto Parts',
        'Mazaj Restaurants',
        'Bayan Education Hub',
        'Zahra Beauty Lounge',
        'Peak Construction',
        'Orchid Pharmacy',
        'Riyadh Smart Logistics',
        'Jood Catering',
        'Sadu Crafts',
        'Marsa Travel & Tours',
        'Qimam Engineering',
        'Wahat Dates Company',
        'Rawabi Furniture',
        'Tareeq Car Rental',
        'Lumina Photography Studio',
        'Safa Dental Clinic',
        'Bonyan Contracting',
        'Misk Perfumes',
        'Kayan Tech Services',
        'Aman Insurance Brokers',
        'Hayat Pharmacy Group',
        'Sunrise Bakery',
        'Alif Bookstore',
        'Namaa Agriculture',
        'Ajyal Nursery',
        'Sawani Laundry',
        'Dar Al Salam Hotel',
        'Etqan Cleaning Services',
        'Ibtikar Labs',
        'Ramz Jewelry',
        'Waha Gym',
    ];

    private const COMPANY_NOTES = [
        'Main branch in Riyadh.',
        'Operates in Riyadh and Jeddah.',
        'Family-owned business.',
        'Established in 2012.',
        'Part of a larger group.',
    ];

    private const PROJECTS = [
        ['Corporate Website', 'Multi-page company website with contact forms.'],
        ['Online Store', 'E-commerce store with cart, checkout and order tracking.'],
        ['Mobile App', 'iOS and Android app for customers.'],
        ['Booking System', 'Online appointment and reservation system.'],
        ['Admin Dashboard', 'Back-office dashboard for managing orders and reports.'],
        ['Customer Portal', 'Self-service portal for invoices and support tickets.'],
        ['Inventory System', 'Stock tracking across warehouses and branches.'],
        ['CRM', 'Lead and customer relationship management.'],
        ['Landing Page', 'Marketing landing page with lead capture.'],
        ['Loyalty Program App', 'Points and rewards for returning customers.'],
    ];

    private const REQUESTS = [
        ['Add dark mode', 'Let users switch between light and dark themes.'],
        ['Integrate Mada payment gateway', 'Accept Mada cards at checkout.'],
        ['Arabic RTL support', 'Full right-to-left layout and Arabic translations.'],
        ['Export invoices to PDF', 'Download any invoice as a PDF from the admin panel.'],
        ['Fix checkout bug on Safari', 'The order button does nothing on iPhone Safari.'],
        ['WhatsApp order notifications', 'Send order status updates to customers on WhatsApp.'],
        ['Add Tabby installments', 'Offer buy-now-pay-later at checkout.'],
        ['Role-based permissions', 'Separate access for admins, staff and accountants.'],
        ['Speed up product listing', 'The page takes over 5 seconds to load on mobile.'],
        ['Email notification on new booking', 'Notify the team by email when a booking is made.'],
        ['Update contact details and footer', 'New phone number, address and social links.'],
        ['Add FAQ section', 'Common questions with an expandable list.'],
        ['Google Analytics integration', 'Track visits and conversions.'],
        ['Migrate to new hosting', 'Move the site and database to a faster server.'],
        ['Monthly sales report email', 'Automatic summary sent on the first day of each month.'],
        ['Multi-branch support', 'Manage stock and orders per branch.'],
        ['Customize invoice template', 'Add logo, VAT number and ZATCA QR code.'],
        ['SEO improvements', 'Meta tags, sitemap and page speed fixes.'],
        ['Product filters and sorting', 'Filter by price, category and availability.'],
        ['Add customer reviews', 'Let customers rate and review products.'],
        ['Two-factor login for admins', 'Extra security for the admin accounts.'],
        ['Import products from Excel', 'Bulk upload of the product catalog.'],
    ];

    private const PRICES = [150, 200, 300, 450, 500, 800, 1000, 1200, 1500, 2000, 2500, 3500, 5000];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        throw_if(
            blank(config('admin.email')) || blank(config('admin.password')),
            RuntimeException::class,
            'Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.'
        );

        User::updateOrCreate(
            ['email' => config('admin.email')],
            [
                'name' => config('admin.name'),
                'password' => config('admin.password'),
            ],
        );

        // Same "random" data on every run. Remove this line for different data each time.
        fake()->seed(2026);

        // Unguarded, so we can set created_at and give the data a believable history.
        Model::unguarded(function () {
            $companyNames = fake()->shuffle(self::COMPANIES);

            foreach (self::CLIENTS as $name) {
                $created = fake()->dateTimeBetween('-18 months', '-12 months');
                $followUpDaysAgo = fake()->randomElement([null, 0, 2, 6, 11, 18, 27, 38, 55, 80, 120]);

                $client = Client::create([
                    'name' => $name,
                    'phone' => fake()->unique()->numerify('05########'),
                    'email' => fake()->boolean(80) ? Str::slug($name, '.') . '@example.com' : null,
                    'last_follow_up' => $followUpDaysAgo === null ? null : today()->subDays($followUpDaysAgo)->toDateString(),
                    'created_at' => $created,
                    'updated_at' => $created,
                ]);

                $companyCount = (int) $this->weighted([1 => 50, 2 => 35, 3 => 15]);

                for ($i = 0; $i < $companyCount; $i++) {
                    $companyName = array_pop($companyNames);

                    if ($companyName === null) {
                        break; // ran out of names
                    }

                    $companyCreated = fake()->dateTimeBetween($created, '-11 months');

                    $company = Company::create([
                        'client_id' => $client->id,
                        'name' => $companyName,
                        'description' => fake()->boolean(60) ? fake()->randomElement(self::COMPANY_NOTES) : null,
                        'created_at' => $companyCreated,
                        'updated_at' => $companyCreated,
                    ]);

                    $templates = fake()->shuffle(self::PROJECTS);

                    foreach (array_slice($templates, 0, fake()->numberBetween(1, 3)) as [$projectName, $projectDescription]) {
                        $projectCreated = fake()->dateTimeBetween('-11 months', '-8 months');

                        $project = Project::create([
                            'company_id' => $company->id,
                            'name' => $projectName,
                            'description' => fake()->boolean(85) ? $projectDescription : null,
                            'created_at' => $projectCreated,
                            'updated_at' => $projectCreated,
                        ]);

                        $this->seedChangeRequests($project);
                    }
                }
            }
        });
    }

    private function seedChangeRequests(Project $project): void
    {
        $templates = fake()->shuffle(self::REQUESTS);

        foreach (array_slice($templates, 0, fake()->numberBetween(0, 7)) as [$title, $description]) {
            $created = fake()->dateTimeBetween('-8 months', '-2 days');
            $status = $this->weighted(['done' => 50, 'pending' => 35, 'cancelled' => 15]);
            $price = fake()->boolean(75) ? fake()->randomElement(self::PRICES) : null;

            // Only finished, priced work can be paid (most of it is).
            $paidAt = $status === 'done' && $price !== null && fake()->boolean(70)
                ? fake()->dateTimeBetween($created, 'now')
                : null;

            ChangeRequest::create([
                'project_id' => $project->id,
                'title' => $title,
                'description' => fake()->boolean(70) ? $description : null,
                'priority' => $this->weighted(['low' => 20, 'medium' => 45, 'high' => 25, 'urgent' => 10]),
                'price' => $price,
                'status' => $status,
                'paid_at' => $paidAt,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }
    }

    /** Picks one key at random, where each key's chance is its weight (50 means "50 out of the total"). */
    private function weighted(array $weights): int|string
    {
        $roll = fake()->numberBetween(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
