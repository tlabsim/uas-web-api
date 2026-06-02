<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Press Release',
                'slug' => 'press-release',
                'description' => 'Official press releases and media communications',
                'icon' => 'newspaper',
                'is_system' => true,
                'sort_order' => 1,
                'meta_schema' => [
                    'required_fields' => [                                             
                    ],                    
                    'extra_fields' => [
                        [
                            'key' => 'contact_person',
                            'label' => 'Contact Person',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                        ],
                        [
                            'key' => 'contact_email',
                            'label' => 'Contact Email',
                            'type' => 'email',
                            'required' => false,
                            'searchable' => false,
                        ],
                        [
                            'key' => 'contact_phone',
                            'label' => 'Contact Phone',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => false,
                        ],
                    ],
                ],
                'attachment_config' => [
                    'required' => false,
                    'max_files' => 5,
                    'allowed_types' => ['image', 'document'],
                ],
            ],
            [
                'name' => 'News',
                'slug' => 'news',
                'description' => 'General news and updates',
                'icon' => 'news',
                'is_system' => true,
                'sort_order' => 2,
                'meta_schema' => [
                    'required_fields' => [
                        [
                            'key' => 'news_category',
                            'label' => 'News Category',
                            'type' => 'select_editable',
                            'required' => true,
                            'searchable' => true,
                            'options' => ['Observation', 'Conference', 'Seminar', 'Research', 'Alumni', 'Sports Event', 'Cultural Event']
                        ],
                    ],
                    'extra_fields' => [                       
                    ],
                ],
                'attachment_config' => [
                    'required' => false,
                    'max_files' => 3,
                    'allowed_types' => ['image'],
                ],
            ],
            [
                'name' => 'Notice',
                'slug' => 'notice',
                'description' => 'Official notices and circulars',
                'icon' => 'bell',
                'is_system' => true,
                'sort_order' => 3,
                'meta_schema' => [
                    'required_fields' => [
                        [
                            'key' => 'notice_type',
                            'label' => 'Notice Type',
                            'type' => 'select_editable',
                            'required' => true,
                            'searchable' => true,
                            'options' => ['General', 'Academic', 'Office Order', 'Admission', 'Examination', 'Event', 'Scholarship'],
                        ],
                        [
                            'key' => 'issue_date',
                            'label' => 'Issue Date',
                            'type' => 'date',
                            'required' => true,
                            'searchable' => true,
                        ],
                        [
                            'key' => 'reference_number',
                            'label' => 'Reference Number',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                            'help_text' => 'Official notice reference number'
                        ],      
                         [
                            'key' => 'target_audience',
                            'label' => 'Target Audience',
                            'type' => 'select',
                            'required' => false,
                            'searchable' => true,
                            'options' => ['Students', 'Faculty', 'Staff', 'All'],
                            'default' => 'All'
                        ],                
                    ],
                    'extra_fields' => [       
                    ],
                ],
                'attachment_config' => [
                    'required' => false,
                    'max_files' => 10,
                    'allowed_types' => ['document'],
                    'help_text' => 'Attach the official notice document (if any)'
                ],
            ],
            [
                'name' => 'Event',
                'slug' => 'event',
                'description' => 'Upcoming events, seminars, workshops, and conferences',
                'icon' => 'calendar',
                'is_system' => true,
                'sort_order' => 4,
                'meta_schema' => [
                    'required_fields' => [
                        [
                            'key' => 'event_category',
                            'label' => 'Event Category',
                            'type' => 'select_editable',
                            'required' => true,
                            'searchable' => true,
                            'options' => ['Seminar', 'Workshop', 'Conference', 'Webinar', 'Meeting', 'Sports', 'Cultural', 'Other'],
                        ],
                        [
                            'key' => 'event_start_date',
                            'label' => 'Start Date',
                            'type' => 'date',
                            'required' => true,
                            'searchable' => true,
                        ],                        
                    ],
                    'extra_fields' => [
                        [
                            'key' => 'event_start_time',
                            'label' => 'Start Time',
                            'type' => 'time',
                            'required' => false,
                            'searchable' => false,
                        ],
                        [
                            'key' => 'venue',
                            'label' => 'Venue',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                        ],
                        [                            
                            'key' => 'event_end_date',
                            'label' => 'End Date',
                            'type' => 'date',
                            'required' => false,
                            'searchable' => true,
                        ],
                        [
                            'key' => 'event_end_time',
                            'label' => 'End Time',
                            'type' => 'time',
                            'required' => false,
                            'searchable' => false,
                        ],
                        [
                            'key' => 'event_type',
                            'label' => 'Event Type',
                            'type' => 'select',
                            'required' => false,
                            'searchable' => true,
                            'options' => ['Seminar', 'Workshop', 'Conference', 'Webinar', 'Meeting', 'Other'],
                        ],
                        [
                            'key' => 'registration_link',
                            'label' => 'Registration Link',
                            'type' => 'url',
                            'required' => false,
                            'searchable' => false,
                        ],
                    ],
                ],
                'attachment_config' => [
                    'required' => false,
                    'max_files' => 5,
                    'allowed_types' => ['image', 'document'],
                ],
            ],
            [
                'name' => 'Activity (NSTU Life)',
                'slug' => 'activity',
                'description' => 'Campus activities and programs',
                'icon' => 'activity',
                'is_system' => true,
                'sort_order' => 5,
                'meta_schema' => [
                    'required_fields' => [        
                        [
                            'key' => 'activity_category',
                            'label' => 'Activity Category',
                            'type' => 'select_editable',
                            'required' => true,
                            'searchable' => true,
                            'options' => ["Campus Event", "Nature @ NSTU", "Cultural Event", "Sports Event", "Observation", "Festival", "Community Service", "Innovation"]
                        ]               
                    ],
                    'extra_fields' => [
                        [
                            'key' => 'organizer',
                            'label' => 'Organizer',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                        ],
                        [
                            'key' => 'location',
                            'label' => 'Location',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                        ],
                    ],
                ],
                'attachment_config' => [
                    'required' => false,
                    'max_files' => 10,
                    'allowed_types' => ['image', 'video'],
                ],
            ],       
            [
                'name' => 'NOC',
                'slug' => 'noc',
                'description' => 'No Objection Certificates',
                'icon' => 'file-check',
                'is_system' => true,
                'sort_order' => 10,
                'meta_schema' => [
                    'required_fields' => [     
                        [
                            'key' => 'issued_to',
                            'label' => 'Issued To',
                            'type' => 'text',
                            'required' => true,
                            'searchable' => true,
                        ],  
                        [
                            'key' => 'issue_date',
                            'label' => 'Issue Date',
                            'type' => 'date',
                            'required' => true,
                            'searchable' => true,
                        ],                                        
                    ],
                    'extra_fields' => [
                         [
                            'key' => 'reference_number',
                            'label' => 'Reference Number',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                        ],                        
                    ],
                ],
                'attachment_config' => [
                    'required' => true,
                    'max_files' => 3,
                    'allowed_types' => ['document'],
                    'help_text' => 'Please attach the official NOC document'
                ],
            ],
            [
                'name' => 'GO',
                'slug' => 'go',
                'description' => 'Government Orders',
                'icon' => 'file-text',
                'is_system' => true,
                'sort_order' => 11,
                'meta_schema' => [
                    'required_fields' => [
                        [
                            'key' => 'issuing_authority',
                            'label' => 'Issuing Authority',
                            'type' => 'text',
                            'required' => true,
                            'searchable' => true,
                        ],
                        [
                            'key' => 'issued_to',
                            'label' => 'Issued To',
                            'type' => 'text',
                            'required' => true,
                            'searchable' => true,
                        ],  
                        [
                            'key' => 'issue_date',
                            'label' => 'Issue Date',
                            'type' => 'date',
                            'required' => true,
                            'searchable' => true,
                        ],    
                    ],
                    'extra_fields' => [    
                         [
                            'key' => 'reference_number',
                            'label' => 'Reference Number',
                            'type' => 'text',
                            'required' => false,
                            'searchable' => true,
                        ],                       
                    ],
                ],
                'attachment_config' => [
                    'required' => true,
                    'max_files' => 5,
                    'allowed_types' => ['document'],
                    'help_text' => 'Please attach the official GO document'
                ],
            ]
        ];

        foreach ($categories as $category) {
            DB::table('post_categories')->insert([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'icon' => $category['icon'],
                'is_system' => $category['is_system'],
                'sort_order' => $category['sort_order'],
                'meta_schema' => json_encode($category['meta_schema']),
                'attachment_config' => json_encode($category['attachment_config']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
