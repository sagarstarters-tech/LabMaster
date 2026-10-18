<?php
/**
 * LabMaster - Safe Pathology Test Master Importer & Expansion Engine
 * Implements 4-Level Duplicate Detection and Absolute Overwrite Protection
 *
 * Rules:
 * - ADDITIVE ONLY: Never overwrites existing tests, parameters, or intervals.
 * - IDEMPOTENT: Can be executed multiple times without generating duplicate records.
 * - TRANSACTIONAL: Ensures database consistency with automatic rollback on failure.
 * - AUDITED: Records all actions in the audit_logs table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

class SafeTestMasterImporter
{
    private PDO $pdo;
    private array $logs = [];
    private array $inserted = [];
    private array $skipped = [];
    private array $errors = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance();
    }

    /**
     * Normalize string for comparison
     */
    public static function normalize(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]/', '', $s);
        return trim($s ?? '');
    }

    /**
     * Ensure required test categories exist in database
     */
    public function ensureCategories(): array
    {
        $categoryMap = [];

        // Fetch existing categories
        $existing = $this->pdo->query("SELECT id, name, code FROM `test_categories`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($existing as $row) {
            $categoryMap[strtoupper($row['code'])] = (int)$row['id'];
            $categoryMap[self::normalize($row['name'])] = (int)$row['id'];
        }

        // Target categories to ensure
        $targets = [
            'HEM' => ['name' => 'Hematology', 'icon' => 'bi-droplet-fill', 'sort' => 1, 'desc' => 'Complete hemogram, coagulation studies, and blood morphology'],
            'BIO' => ['name' => 'Clinical Biochemistry', 'icon' => 'bi-pie-chart-fill', 'sort' => 2, 'desc' => 'Clinical chemistry, metabolic profiles, renal and liver function assays'],
            'ENDO' => ['name' => 'Endocrinology & Hormones', 'icon' => 'bi-activity', 'sort' => 3, 'desc' => 'Thyroid hormones, reproductive endocrinology, and metabolic assays'],
            'SERO' => ['name' => 'Serology & Immunology', 'icon' => 'bi-shield-check', 'sort' => 4, 'desc' => 'Infectious serology, febrile agglutinins, and autoimmune screens'],
            'CP' => ['name' => 'Clinical Pathology & Urinalysis', 'icon' => 'bi-cup-straw', 'sort' => 5, 'desc' => 'Urine routine & microscopy, body fluids, and stool examination'],
            'MICRO' => ['name' => 'Microbiology', 'icon' => 'bi-bug-fill', 'sort' => 6, 'desc' => 'Bacteriology, culture and antimicrobial susceptibility testing'],
            'HISTO' => ['name' => 'Histopathology & Cytology', 'icon' => 'bi-file-medical', 'sort' => 7, 'desc' => 'Biopsy tissue analysis and exfoliative cytology'],
            'VIRAL' => ['name' => 'Viral Markers', 'icon' => 'bi-shield-exclamation', 'sort' => 8, 'desc' => 'Hepatitis and HIV viral serology with high confidentiality']
        ];

        foreach ($targets as $code => $data) {
            if (!isset($categoryMap[$code])) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO `test_categories` (`name`, `code`, `description`, `icon`, `sort_order`, `is_active`)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$data['name'], $code, $data['desc'], $data['icon'], $data['sort']]);
                $newId = (int)$this->pdo->lastInsertId();
                $categoryMap[$code] = $newId;
                $categoryMap[self::normalize($data['name'])] = $newId;
                $this->logs[] = "Created missing category: '{$data['name']}' (Code: {$code}, ID: {$newId}).";
            }
        }

        return $categoryMap;
    }

    /**
     * Return candidate catalog of tests, parameters, and reference intervals
     */
    public function getCandidateCatalog(): array
    {
        return [
            // =========================================================================
            // 1. HEMATOLOGY TESTS
            // =========================================================================
            [
                'category_code' => 'HEM',
                'test_code' => 'HB',
                'test_name' => 'Hemoglobin (Hb)',
                'short_name' => 'Hb',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 120.00,
                'cost_price' => 20.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'SLS-Hemoglobin Method / Photometric Cyanmethemoglobin',
                'instructions' => 'No fasting required. Collect 2mL whole blood in K2/K3 EDTA purple-top tube. Invert 8-10 times gently.',
                'clinical_significance' => 'Primary investigation for diagnosis and monitoring of anemia, polycythemia, blood loss, and response to hematinics.',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Hemoglobin (Hb)',
                        'code' => 'HB_STANDALONE',
                        'unit' => 'g/dL',
                        'type' => 'numeric',
                        'crit_low' => 7.0,
                        'crit_high' => 20.0,
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 13.0, 'max_val' => 17.0, 'display' => '13.0 - 17.0 g/dL', 'interp' => 'Normal adult male range'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 12.0, 'max_val' => 15.0, 'display' => '12.0 - 15.0 g/dL', 'interp' => 'Normal adult female range']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'TLC',
                'test_name' => 'Total Leukocyte Count (TLC / WBC)',
                'short_name' => 'TLC',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 120.00,
                'cost_price' => 20.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Automated Electrical Impedance / Flow Cytometry',
                'instructions' => 'Collect 2mL EDTA whole blood.',
                'clinical_significance' => 'Evaluation of leukocytosis (infections, leukemias, inflammation) and leukopenia (bone marrow depression, viral infections).',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Total Leukocyte Count (TLC)',
                        'code' => 'TLC_STANDALONE',
                        'unit' => '/µL',
                        'type' => 'numeric',
                        'crit_low' => 2000.0,
                        'crit_high' => 30000.0,
                        'intervals' => [
                            ['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 4000.0, 'max_val' => 11000.0, 'display' => '4,000 - 11,000 /µL', 'interp' => 'Adult physiological reference range']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'DLC',
                'test_name' => 'Differential Leukocyte Count (DLC)',
                'short_name' => 'DLC',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 150.00,
                'cost_price' => 30.00,
                'turnaround_time' => 'Same Day (2-4 Hours)',
                'method' => 'Automated Flow Cytometry & Peripheral Blood Smear Microscopy',
                'instructions' => 'EDTA whole blood.',
                'clinical_significance' => 'Quantifies percentages of individual leukocyte sub-populations to characterize infections, allergies, and hematologic malignancies.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Neutrophils', 'code' => 'DLC_NEUT', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 40.0, 'max_val' => 75.0, 'display' => '40.0 - 75.0 %', 'interp' => 'Bacterial response']]],
                    ['name' => 'Lymphocytes', 'code' => 'DLC_LYMPH', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 20.0, 'max_val' => 45.0, 'display' => '20.0 - 45.0 %', 'interp' => 'Viral and adaptive immune response']]],
                    ['name' => 'Eosinophils', 'code' => 'DLC_EOS', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 1.0, 'max_val' => 6.0, 'display' => '1.0 - 6.0 %', 'interp' => 'Allergic and parasitic response']]],
                    ['name' => 'Monocytes', 'code' => 'DLC_MONO', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 2.0, 'max_val' => 10.0, 'display' => '2.0 - 10.0 %', 'interp' => 'Chronic inflammation & phagocytosis']]],
                    ['name' => 'Basophils', 'code' => 'DLC_BASO', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 1.0, 'display' => '0.0 - 1.0 %', 'interp' => 'Histamine release & myeloproliferative disorders']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'PLT',
                'test_name' => 'Platelet Count & Indices',
                'short_name' => 'Platelet Count',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 150.00,
                'cost_price' => 25.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Automated Impedance / Optical Flow Cytometry',
                'instructions' => 'EDTA whole blood. Ensure complete mixing to prevent micro-clotting.',
                'clinical_significance' => 'Critical in evaluating bleeding diathesis, dengue thrombocytopenia, ITP, and bone marrow megakaryopoiesis.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Platelet Count', 'code' => 'PLT_COUNT', 'unit' => '/µL', 'type' => 'numeric', 'crit_low' => 50000.0, 'crit_high' => 1000000.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 150000.0, 'max_val' => 450000.0, 'display' => '150,000 - 450,000 /µL (1.5 - 4.5 Lakhs/µL)', 'interp' => 'Normal hemostatic count']]],
                    ['name' => 'Mean Platelet Volume (MPV)', 'code' => 'PLT_MPV', 'unit' => 'fL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 7.5, 'max_val' => 11.5, 'display' => '7.5 - 11.5 fL', 'interp' => 'Marker of platelet production and size']]],
                    ['name' => 'Plateletcrit (PCT)', 'code' => 'PLT_PCT', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.15, 'max_val' => 0.40, 'display' => '0.15 - 0.40 %', 'interp' => 'Total platelet volume fraction']]],
                    ['name' => 'Platelet Distribution Width (PDW)', 'code' => 'PLT_PDW', 'unit' => 'fL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 9.0, 'max_val' => 17.0, 'display' => '9.0 - 17.0 fL', 'interp' => 'Platelet anisocytosis index']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'ESR',
                'test_name' => 'Erythrocyte Sedimentation Rate (ESR Alone)',
                'short_name' => 'ESR',
                'specimen_type' => 'Whole Blood (Sodium Citrate / EDTA)',
                'department' => 'Hematology',
                'price' => 100.00,
                'cost_price' => 15.00,
                'turnaround_time' => 'Same Day (2 Hours)',
                'method' => 'Westergren Method (Modified Photometric / Manual Tube)',
                'instructions' => 'Citrated or EDTA whole blood. Perform within 4 hours of venipuncture.',
                'clinical_significance' => 'Non-specific marker for acute and chronic systemic inflammation, infections, autoimmune disorders, and occult malignancies.',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Erythrocyte Sedimentation Rate (ESR)',
                        'code' => 'ESR_VAL',
                        'unit' => 'mm/1st hr',
                        'type' => 'numeric',
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 15.0, 'display' => '0 - 15 mm/1st hr', 'interp' => 'Male range'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 20.0, 'display' => '0 - 20 mm/1st hr', 'interp' => 'Female range']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'PBS',
                'test_name' => 'Peripheral Blood Smear Examination (PBS / PS)',
                'short_name' => 'PBS Examination',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 250.00,
                'cost_price' => 40.00,
                'turnaround_time' => 'Same Day (4-6 Hours)',
                'method' => 'Leishman Stained Smear Microscopy by Pathologist',
                'instructions' => 'Prepare fresh thin blood films immediately after collection.',
                'clinical_significance' => 'Pathologist review of cellular morphology, blast cells, hemoparasites (malaria, filaria), and dysplastic changes.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'RBC Morphology', 'code' => 'PBS_RBC', 'unit' => '', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Normocytic Normochromic', 'interp' => 'Normal cell size and chromia']]],
                    ['name' => 'WBC Morphology', 'code' => 'PBS_WBC', 'unit' => '', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Normal maturity; No atypical or blast cells seen', 'interp' => 'Normal leukocyte architecture']]],
                    ['name' => 'Platelets on Smear', 'code' => 'PBS_PLT', 'unit' => '', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Adequate on smear (10-15 per oil immersion field)', 'interp' => 'Correlates with normal count']]],
                    ['name' => 'Hemoparasites', 'code' => 'PBS_PARA', 'unit' => '', 'type' => 'dropdown', 'dropdown' => 'Not Seen, Plasmodium vivax seen, Plasmodium falciparum seen, Microfilaria seen', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Not Seen', 'interp' => 'Negative for hemoparasites']]],
                    ['name' => 'Smear Impression', 'code' => 'PBS_IMPR', 'unit' => '', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Normal Peripheral Blood Smear Study', 'interp' => 'Pathologist impression']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'AEC',
                'test_name' => 'Absolute Eosinophil Count (AEC)',
                'short_name' => 'AEC',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 150.00,
                'cost_price' => 25.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Automated Flow Cytometry / Fuchs-Rosenthal Chamber Counting',
                'instructions' => 'EDTA whole blood.',
                'clinical_significance' => 'Quantifies absolute eosinophils for allergic rhinitis, bronchial asthma, tropical pulmonary eosinophilia, and drug hypersensitivity.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Absolute Eosinophil Count (AEC)', 'code' => 'AEC_VAL', 'unit' => '/µL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 40.0, 'max_val' => 450.0, 'display' => '40 - 450 /µL', 'interp' => 'Normal circulating eosinophils']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'RETIC',
                'test_name' => 'Reticulocyte Count & Index',
                'short_name' => 'Retic Count',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 200.00,
                'cost_price' => 35.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => 'Supravital Staining (New Methylene Blue) / Automated Flow Cytometry',
                'instructions' => 'EDTA whole blood.',
                'clinical_significance' => 'Distinguishes between hyperproliferative (hemolysis, acute bleed response) and hypoproliferative anemias (aplastic, nutritional).',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Reticulocyte Percentage', 'code' => 'RETIC_PCT', 'unit' => '%', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.5, 'max_val' => 2.0, 'display' => '0.5 - 2.0 %', 'interp' => 'Normal marrow output']]],
                    ['name' => 'Corrected Reticulocyte Count', 'code' => 'RETIC_CORR', 'unit' => '%', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.5, 'max_val' => 2.0, 'display' => '0.5 - 2.0 %', 'interp' => 'Corrected for patient hematocrit']]],
                    ['name' => 'Reticulocyte Production Index (RPI)', 'code' => 'RETIC_RPI', 'unit' => 'Index', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 1.0, 'max_val' => 2.5, 'display' => '1.0 - 2.5 (>2.0 indicates adequate erythropoiesis)', 'interp' => 'Marrow response index']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'PT-INR',
                'test_name' => 'Prothrombin Time with INR (PT/INR)',
                'short_name' => 'PT/INR',
                'specimen_type' => 'Citrated Plasma (3.2% Sodium Citrate Blue Top)',
                'department' => 'Coagulation',
                'price' => 250.00,
                'cost_price' => 45.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Photo-optical / Electromechanical Clot Detection',
                'instructions' => 'Exact 9:1 blood to anticoagulant ratio required (2.7mL blood to 0.3mL citrate). Centrifuge immediately at 2500g for 15 mins.',
                'clinical_significance' => 'Evaluates extrinsic and common coagulation pathways. Essential for oral vitamin K antagonist (Warfarin) therapy and liver cirrhosis workup.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Prothrombin Time (Patient)', 'code' => 'PT_TIME', 'unit' => 'Seconds', 'type' => 'numeric', 'crit_high' => 35.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 11.0, 'max_val' => 14.5, 'display' => '11.0 - 14.5 Seconds', 'interp' => 'Normal control time']]],
                    ['name' => 'Control Time (Mean Normal PT)', 'code' => 'PT_CONTROL', 'unit' => 'Seconds', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 11.5, 'max_val' => 13.0, 'display' => '11.5 - 13.0 Seconds', 'interp' => 'Laboratory lot control']]],
                    ['name' => 'International Normalized Ratio (INR)', 'code' => 'PT_INR', 'unit' => 'Ratio', 'type' => 'formula', 'crit_high' => 4.5, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.85, 'max_val' => 1.15, 'display' => '0.85 - 1.15 (Therapeutic Warfarin: 2.0 - 3.0)', 'interp' => 'Standardized anticoagulant ratio']]],
                    ['name' => 'ISI (International Sensitivity Index)', 'code' => 'PT_ISI', 'unit' => '', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => '1.00 - 1.15', 'interp' => 'Thromboplastin sensitivity index']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'APTT',
                'test_name' => 'Activated Partial Thromboplastin Time (APTT)',
                'short_name' => 'APTT',
                'specimen_type' => 'Citrated Plasma (3.2% Sodium Citrate Blue Top)',
                'department' => 'Coagulation',
                'price' => 300.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Photo-optical Clot Detection with Micronized Silica Reagent',
                'instructions' => 'Citrated platelet-poor plasma.',
                'clinical_significance' => 'Evaluates intrinsic and common pathways (Factors VIII, IX, XI, XII). Screens for hemophilia and monitors unfractionated heparin therapy.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'APTT (Patient)', 'code' => 'APTT_TIME', 'unit' => 'Seconds', 'type' => 'numeric', 'crit_high' => 70.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 25.0, 'max_val' => 38.0, 'display' => '25.0 - 38.0 Seconds', 'interp' => 'Normal intrinsic time']]],
                    ['name' => 'APTT Control Time', 'code' => 'APTT_CONTROL', 'unit' => 'Seconds', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 28.0, 'max_val' => 32.0, 'display' => '28.0 - 32.0 Seconds', 'interp' => 'Lot normal control']]],
                    ['name' => 'APTT Ratio', 'code' => 'APTT_RATIO', 'unit' => 'Ratio', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.8, 'max_val' => 1.2, 'display' => '0.8 - 1.2 (Therapeutic Heparin: 1.5 - 2.5)', 'interp' => 'Heparin therapy indicator']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'BT-CT',
                'test_name' => 'Bleeding Time & Clotting Time (BT / CT)',
                'short_name' => 'BT/CT',
                'specimen_type' => 'Capillary / Whole Blood (In-vivo & Glass Tube)',
                'department' => 'Coagulation',
                'price' => 150.00,
                'cost_price' => 20.00,
                'turnaround_time' => 'Same Day (1 Hour)',
                'method' => 'Duke / Ivy Method for BT & Wrights / Lee-White Method for CT',
                'instructions' => 'Bedside or lab procedure. Patient should avoid aspirin or anti-platelet agents for 7 days prior.',
                'clinical_significance' => 'Routine pre-operative bedside screening for primary (platelet-vessel wall) and secondary hemostatic function.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Bleeding Time (BT)', 'code' => 'BT_TIME', 'unit' => 'Minutes', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => '1 - 5 Minutes (Duke Method)', 'interp' => 'Platelet interaction with subendothelium']]],
                    ['name' => 'Clotting Time (CT)', 'code' => 'CT_TIME', 'unit' => 'Minutes', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => '5 - 11 Minutes (Lee-White Tube Method)', 'interp' => 'Secondary fibrin formation']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'BLOOD-GRP',
                'test_name' => 'Blood Grouping & Rh Typing (ABO & RhD)',
                'short_name' => 'Blood Group',
                'specimen_type' => 'Whole Blood (EDTA / Plain)',
                'department' => 'Immunohematology',
                'price' => 150.00,
                'cost_price' => 25.00,
                'turnaround_time' => 'Same Day (1-2 Hours)',
                'method' => 'Direct Hemagglutination (Slide & Tube Test with Anti-A, Anti-B, Anti-D)',
                'instructions' => 'Collect 2mL EDTA or plain blood.',
                'clinical_significance' => 'Fundamental requirement for blood transfusion safety, surgical preparation, and Rh incompatibility prevention in pregnancy.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'ABO Blood Group', 'code' => 'ABO_GROUP', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'A', 'B', 'AB', 'O'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Group A, B, AB, or O', 'interp' => 'Determined by erythrocyte surface antigens']]],
                    ['name' => 'Rh(D) Factor', 'code' => 'RH_FACTOR', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'POSITIVE', 'NEGATIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Positive (+) / Negative (-)', 'interp' => 'Presence or absence of RhD antigen']]],
                    ['name' => 'Final Blood Group Result', 'code' => 'BLOOD_GROUP_FINAL', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'A Positive (A+)', 'A Negative (A-)', 'B Positive (B+)', 'B Negative (B-)', 'AB Positive (AB+)', 'AB Negative (AB-)', 'O Positive (O+)', 'O Negative (O-)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Documented on Transfusion Card', 'interp' => 'Clinical grouping']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'SICKLE',
                'test_name' => 'Sickling Test (Sodium Metabisulfite Slide Test)',
                'short_name' => 'Sickling Test',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology',
                'price' => 200.00,
                'cost_price' => 30.00,
                'turnaround_time' => 'Same Day (4 Hours / 24h Observation)',
                'method' => 'Chemical Deoxygenation with 2% Sodium Metabisulfite Solution',
                'instructions' => 'EDTA whole blood.',
                'clinical_significance' => 'Screening test for the presence of Hemoglobin S (Sickle Cell Anemia / Sickle Trait). Positive results require confirmation by HPLC / Hemoglobin Electrophoresis.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Sickling Phenomenon', 'code' => 'SICKLE_RESULT', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NEGATIVE (No sickling observed)', 'POSITIVE (Sickled red cells observed)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NEGATIVE', 'interp' => 'Absence of sickle hemoglobins']]]
                ]
            ],
            [
                'category_code' => 'HEM',
                'test_code' => 'MALARIA',
                'test_name' => 'Malaria Parasite Examination (Smear & Antigen Combo)',
                'short_name' => 'Malaria Panel',
                'specimen_type' => 'Whole Blood (EDTA)',
                'department' => 'Hematology & Parasitology',
                'price' => 300.00,
                'cost_price' => 60.00,
                'turnaround_time' => 'Same Day (2 Hours - STAT)',
                'method' => 'Thick & Thin Blood Film Microscopy + Immunochromatographic Rapid Antigen (Pf / Pan)',
                'instructions' => 'Collect blood preferably during or immediately after febrile spikes.',
                'clinical_significance' => 'Emergency diagnostic workup for acute malaria, differentiating between P. falciparum (malignant tertian) and non-falciparum species.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Microscopic Examination (Thick & Thin Smear)', 'code' => 'MP_SMEAR', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'No Malaria Parasites (NMP) seen', 'Plasmodium vivax trophozoites / gametocytes seen', 'Plasmodium falciparum ring forms / gametocytes seen', 'Mixed Plasmodium infection seen'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'No Malaria Parasites (NMP) seen', 'interp' => 'Examined under 100 oil immersion fields']]],
                    ['name' => 'Plasmodium falciparum (Pf) Antigen (HRP-II)', 'code' => 'MP_PF_AG', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative)', 'REACTIVE (Positive)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'Pf specific histidine-rich protein']]],
                    ['name' => 'Plasmodium vivax / Pan Antigen (pLDH)', 'code' => 'MP_PAN_AG', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative)', 'REACTIVE (Positive)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'Pan-malarial parasite lactate dehydrogenase']]]
                ]
            ],

            // =========================================================================
            // 2. BIOCHEMISTRY TESTS
            // =========================================================================
            [
                'category_code' => 'BIO',
                'test_code' => 'RBS',
                'test_name' => 'Random Blood Glucose (RBS)',
                'short_name' => 'RBS',
                'specimen_type' => 'Fluoride Plasma / Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 100.00,
                'cost_price' => 15.00,
                'turnaround_time' => 'Same Day (1-2 Hours)',
                'method' => 'GOD-POD (Glucose Oxidase - Peroxidase) Enzymatic Colorimetric',
                'instructions' => 'Sample taken without regard to time since the last meal.',
                'clinical_significance' => 'Emergency and casual evaluation of hyper/hypoglycemia. Values ≥ 200 mg/dL with symptoms suggest diabetes mellitus.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Random Blood Glucose', 'code' => 'RBS_VAL', 'unit' => 'mg/dL', 'type' => 'numeric', 'crit_low' => 50.0, 'crit_high' => 350.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 70.0, 'max_val' => 140.0, 'display' => '70.0 - 140.0 mg/dL (Normal)', 'interp' => 'Casual glycemic interval']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'OGTT',
                'test_name' => 'Oral Glucose Tolerance Test (OGTT - 75g 3-Point)',
                'short_name' => 'OGTT',
                'specimen_type' => 'Fluoride Plasma',
                'department' => 'Clinical Biochemistry',
                'price' => 350.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Timed Series GOD-POD Photometric',
                'instructions' => 'Fasting for 8-12 hours. Patient ingests 75g anhydrous oral glucose dissolved in 300mL water over 5 minutes. Remain seated throughout test.',
                'clinical_significance' => 'Gold standard dynamic diagnostic test for impaired glucose tolerance and Gestational Diabetes Mellitus (GDM / DIPSI criteria).',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Fasting Blood Glucose', 'code' => 'OGTT_FAST', 'unit' => 'mg/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 70.0, 'max_val' => 100.0, 'display' => '70.0 - 100.0 mg/dL', 'interp' => 'Fasting threshold']]],
                    ['name' => '1-Hour Post Glucose Load', 'code' => 'OGTT_1HR', 'unit' => 'mg/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 70.0, 'max_val' => 140.0, 'display' => '< 140.0 mg/dL (GDM screening cutoff: < 140 mg/dL)', 'interp' => 'Peak absorption phase']]],
                    ['name' => '2-Hour Post Glucose Load', 'code' => 'OGTT_2HR', 'unit' => 'mg/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 70.0, 'max_val' => 140.0, 'display' => '< 140.0 Normal (140-199 Impaired, ≥ 200 Diabetic)', 'interp' => 'Diagnostic clearance point']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'CREAT-STANDALONE',
                'test_name' => 'Serum Creatinine with eGFR',
                'short_name' => 'Serum Creatinine',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 180.00,
                'cost_price' => 25.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Modified Jaffe Kinetic / Enzymatic Creatininase',
                'instructions' => 'Avoid strenuous physical activity and excessive red meat ingestion for 24h prior.',
                'clinical_significance' => 'Essential kidney function metric. Mandatory prior to intravenous radiographic contrast injection and for drug dosage adjustments in CKD.',
                'is_package' => 1,
                'parameters' => [
                    [
                        'name' => 'Serum Creatinine',
                        'code' => 'S_CREAT',
                        'unit' => 'mg/dL',
                        'type' => 'numeric',
                        'crit_high' => 5.0,
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.70, 'max_val' => 1.30, 'display' => '0.70 - 1.30 mg/dL', 'interp' => 'Adult male range'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.50, 'max_val' => 1.10, 'display' => '0.50 - 1.10 mg/dL', 'interp' => 'Adult female range']
                        ]
                    ],
                    [
                        'name' => 'Estimated GFR (eGFR CKD-EPI Formula)',
                        'code' => 'EGFR_VAL',
                        'unit' => 'mL/min/1.73m²',
                        'type' => 'formula',
                        'intervals' => [
                            ['gender' => 'All', 'age_min' => 18, 'age_max' => 120, 'min_val' => 90.0, 'max_val' => 150.0, 'display' => '> 90 Normal (60-89 Mild decline, 30-59 Moderate, 15-29 Severe, <15 Kidney Failure)', 'interp' => 'KDIGO CKD Staging']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'URIC-ACID',
                'test_name' => 'Serum Uric Acid',
                'short_name' => 'Uric Acid',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 180.00,
                'cost_price' => 25.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Uricase-Peroxidase (Enzymatic Colorimetric)',
                'instructions' => 'Overnight fasting preferred. Avoid alcohol and purine-rich diet prior to test.',
                'clinical_significance' => 'Diagnostic parameter for gouty arthritis, hyperuricemia, renal uric acid nephrolithiasis, and preeclampsia screening.',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Serum Uric Acid',
                        'code' => 'URIC_STANDALONE',
                        'unit' => 'mg/dL',
                        'type' => 'numeric',
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 3.5, 'max_val' => 7.2, 'display' => '3.5 - 7.2 mg/dL', 'interp' => 'Male reference range'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 2.6, 'max_val' => 6.0, 'display' => '2.6 - 6.0 mg/dL', 'interp' => 'Female reference range']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'GGT',
                'test_name' => 'Gamma-Glutamyl Transferase (GGT / GGTP)',
                'short_name' => 'GGT',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 250.00,
                'cost_price' => 35.00,
                'turnaround_time' => 'Same Day (2-4 Hours)',
                'method' => 'Szasz Kinetic Photometric (L-gamma-glutamyl-3-carboxy-4-nitroanilide)',
                'instructions' => 'Fasting preferred. Abstain from alcohol for 24-48 hours before testing.',
                'clinical_significance' => 'Most sensitive enzymatic indicator of biliary ductal pathology and chronic alcohol-induced microsomal liver injury. Differentiates hepatic from bone ALP elevation.',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Gamma-Glutamyl Transferase (GGT)',
                        'code' => 'GGT_VAL',
                        'unit' => 'U/L',
                        'type' => 'numeric',
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 10.0, 'max_val' => 71.0, 'display' => '10 - 71 U/L', 'interp' => 'Male range'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 6.0, 'max_val' => 42.0, 'display' => '6 - 42 U/L', 'interp' => 'Female range']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'ELECTROLYTES',
                'test_name' => 'Serum Electrolytes (Sodium, Potassium, Chloride)',
                'short_name' => 'Serum Electrolytes',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 450.00,
                'cost_price' => 60.00,
                'turnaround_time' => 'Same Day (1-2 Hours - STAT)',
                'method' => 'Ion Selective Electrode (ISE Direct Method)',
                'instructions' => 'Non-hemolyzed serum is mandatory. Centrifuge immediately to avoid spurious potassium elevation from RBC leakage.',
                'clinical_significance' => 'Critical panel for electrolyte balance, dehydration, heart failure, renal dysfunction, and acid-base disturbances.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Serum Sodium (Na+)', 'code' => 'ISE_NA', 'unit' => 'mEq/L', 'type' => 'numeric', 'crit_low' => 120.0, 'crit_high' => 160.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 135.0, 'max_val' => 145.0, 'display' => '135.0 - 145.0 mEq/L', 'interp' => 'Extracellular cation']]],
                    ['name' => 'Serum Potassium (K+)', 'code' => 'ISE_K', 'unit' => 'mEq/L', 'type' => 'numeric', 'crit_low' => 2.8, 'crit_high' => 6.2, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 3.5, 'max_val' => 5.1, 'display' => '3.5 - 5.1 mEq/L', 'interp' => 'Intracellular cation (cardiac conduction)']]],
                    ['name' => 'Serum Chloride (Cl-)', 'code' => 'ISE_CL', 'unit' => 'mEq/L', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 96.0, 'max_val' => 106.0, 'display' => '96.0 - 106.0 mEq/L', 'interp' => 'Major extracellular anion']]],
                    ['name' => 'Anion Gap', 'code' => 'ISE_AGAP', 'unit' => 'mEq/L', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 8.0, 'max_val' => 16.0, 'display' => '8.0 - 16.0 mEq/L (Na - [Cl + HCO3])', 'interp' => 'Metabolic acidosis indicator']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'CALCIUM',
                'test_name' => 'Serum Calcium (Total & Corrected)',
                'short_name' => 'Serum Calcium',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 200.00,
                'cost_price' => 30.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Arsenazo III / O-Cresolphthalein Complexone Colorimetric',
                'instructions' => 'Fasting serum preferred. Avoid prolonged venous stasis during venipuncture.',
                'clinical_significance' => 'Crucial for parathyroid disorders, bone disease, renal failure, pancreatitis, tetany, and malignancy-associated hypercalcemia.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Total Serum Calcium', 'code' => 'CA_TOTAL', 'unit' => 'mg/dL', 'type' => 'numeric', 'crit_low' => 6.5, 'crit_high' => 13.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 8.5, 'max_val' => 10.5, 'display' => '8.5 - 10.5 mg/dL', 'interp' => 'Total measured calcium']]],
                    ['name' => 'Corrected Calcium (Albumin Adjusted)', 'code' => 'CA_CORRECTED', 'unit' => 'mg/dL', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 8.5, 'max_val' => 10.5, 'display' => '8.5 - 10.5 mg/dL (Adjusted for Serum Albumin)', 'interp' => 'True physiologically active calcium estimate']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'PHOSPHORUS',
                'test_name' => 'Serum Inorganic Phosphorus',
                'short_name' => 'Serum Phosphorus',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 180.00,
                'cost_price' => 25.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Phosphomolybdate UV Method',
                'instructions' => 'Morning fasting serum. Hemolysis causes falsely elevated results.',
                'clinical_significance' => 'Essential alongside calcium in evaluating chronic kidney disease, hypoparathyroidism, rickets, and osteomalacia.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Serum Inorganic Phosphorus', 'code' => 'PHOS_VAL', 'unit' => 'mg/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 2.5, 'max_val' => 4.5, 'display' => '2.5 - 4.5 mg/dL', 'interp' => 'Adult range']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'MAGNESIUM',
                'test_name' => 'Serum Magnesium',
                'short_name' => 'Serum Magnesium',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 250.00,
                'cost_price' => 35.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Calmagite / Xylidyl Blue Colorimetric',
                'instructions' => 'Hemolysis-free plain serum.',
                'clinical_significance' => 'Assesses neuromuscular excitability, cardiac arrhythmias, refractory hypokalemia/hypocalcemia, and eclampsia therapy.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Serum Magnesium', 'code' => 'MG_VAL', 'unit' => 'mg/dL', 'type' => 'numeric', 'crit_low' => 1.0, 'crit_high' => 4.5, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 1.7, 'max_val' => 2.4, 'display' => '1.7 - 2.4 mg/dL', 'interp' => 'Therapeutic range in preeclampsia: 4.8 - 8.4 mg/dL']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'AMYLASE',
                'test_name' => 'Serum Amylase',
                'short_name' => 'Serum Amylase',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 350.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (2 Hours - STAT)',
                'method' => 'CNPG3 Kinetic Enzymatic',
                'instructions' => 'Plain serum. Avoid saliva contamination.',
                'clinical_significance' => 'Diagnostic enzyme for acute pancreatitis, pancreatic pseudocyst, and acute abdomen differential.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Serum Amylase', 'code' => 'AMYLASE_VAL', 'unit' => 'U/L', 'type' => 'numeric', 'crit_high' => 400.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 28.0, 'max_val' => 100.0, 'display' => '28 - 100 U/L', 'interp' => 'Values > 3x upper limit indicate acute pancreatitis']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'LIPASE',
                'test_name' => 'Serum Lipase',
                'short_name' => 'Serum Lipase',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 450.00,
                'cost_price' => 70.00,
                'turnaround_time' => 'Same Day (2 Hours - STAT)',
                'method' => 'Enzymatic Colorimetric (Methylresorufin)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Superior clinical specificity to amylase for acute pancreatitis; remains elevated longer (7-14 days).',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Serum Lipase', 'code' => 'LIPASE_VAL', 'unit' => 'U/L', 'type' => 'numeric', 'crit_high' => 300.0, 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 13.0, 'max_val' => 60.0, 'display' => '13 - 60 U/L', 'interp' => 'Highly specific for pancreatic acinar injury']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'CPK',
                'test_name' => 'Creatine Kinase Total (CK / CPK)',
                'short_name' => 'CPK Total',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry',
                'price' => 350.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'IFCC / DGKC Kinetic UV Photometric',
                'instructions' => 'Avoid intramuscular injections and intense physical exercise prior to blood draw.',
                'clinical_significance' => 'Evaluation of skeletal muscle necrosis (rhabdomyolysis), myositis, muscular dystrophies, and myocardial damage.',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Creatine Kinase (CK / CPK Total)',
                        'code' => 'CPK_TOTAL',
                        'unit' => 'U/L',
                        'type' => 'numeric',
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 39.0, 'max_val' => 308.0, 'display' => '39 - 308 U/L', 'interp' => 'Male adult range'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 26.0, 'max_val' => 192.0, 'display' => '26 - 192 U/L', 'interp' => 'Female adult range']
                        ]
                    ]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'LDH',
                'test_name' => 'Lactate Dehydrogenase (LDH)',
                'short_name' => 'LDH',
                'specimen_type' => 'Plain Serum (Hemolysis-Free)',
                'department' => 'Clinical Biochemistry',
                'price' => 300.00,
                'cost_price' => 45.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'IFCC UV Kinetic Photometric',
                'instructions' => 'Strictly avoid hemolysis; RBCs contain 150x serum LDH.',
                'clinical_significance' => 'Tumor marker in lymphoma, germ cell tumors, and marker of hemolytic anemias, tissue necrosis, and severe infection.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Lactate Dehydrogenase (LDH)', 'code' => 'LDH_VAL', 'unit' => 'U/L', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 140.0, 'max_val' => 280.0, 'display' => '140 - 280 U/L', 'interp' => 'General tissue turnover reference']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'IRON-PROFILE',
                'test_name' => 'Iron Studies & TIBC Profile',
                'short_name' => 'Iron Profile',
                'specimen_type' => 'Plain Serum (Morning Fasting)',
                'department' => 'Clinical Biochemistry',
                'price' => 650.00,
                'cost_price' => 100.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Ferrozine Photometric (Direct Serum Iron & UIBC)',
                'instructions' => 'Morning fasting sample preferred due to diurnal variation in iron levels. Avoid iron supplements for 24h.',
                'clinical_significance' => 'Essential differential diagnosis of microcytic anemias (Iron deficiency vs. Thalassemia trait vs. Anemia of Chronic Disease) and hemochromatosis.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Serum Iron', 'code' => 'IRON_VAL', 'unit' => 'µg/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 60.0, 'max_val' => 170.0, 'display' => '60.0 - 170.0 µg/dL', 'interp' => 'Circulating ferric iron']]],
                    ['name' => 'Total Iron Binding Capacity (TIBC)', 'code' => 'TIBC_VAL', 'unit' => 'µg/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 240.0, 'max_val' => 450.0, 'display' => '240.0 - 450.0 µg/dL', 'interp' => 'Transferrin saturation capacity']]],
                    ['name' => 'Unsaturated Iron Binding Capacity (UIBC)', 'code' => 'UIBC_VAL', 'unit' => 'µg/dL', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 140.0, 'max_val' => 300.0, 'display' => '140.0 - 300.0 µg/dL', 'interp' => 'Available iron binding sites']]],
                    ['name' => 'Transferrin Saturation (%)', 'code' => 'TRANS_SAT', 'unit' => '%', 'type' => 'formula', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 20.0, 'max_val' => 50.0, 'display' => '20.0 - 50.0 % (< 15% indicates iron deficiency)', 'interp' => 'Iron saturation ratio']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'FERRITIN',
                'test_name' => 'Serum Ferritin Assay',
                'short_name' => 'Serum Ferritin',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry / Immunoassays',
                'price' => 600.00,
                'cost_price' => 90.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => 'Chemiluminescent Microparticle Immunoassay (CMIA / ECLIA)',
                'instructions' => 'Fasting plain serum.',
                'clinical_significance' => 'Most sensitive laboratory test for total body iron depletion (stores). Also acts as an acute-phase reactant in hyperferritinemic syndromes.',
                'is_package' => 0,
                'parameters' => [
                    [
                        'name' => 'Serum Ferritin',
                        'code' => 'FERRITIN_VAL',
                        'unit' => 'ng/mL',
                        'type' => 'numeric',
                        'intervals' => [
                            ['gender' => 'Male', 'age_min' => 0, 'age_max' => 120, 'min_val' => 30.0, 'max_val' => 400.0, 'display' => '30.0 - 400.0 ng/mL', 'interp' => 'Male range (< 30 indicates depleted iron stores)'],
                            ['gender' => 'Female', 'age_min' => 0, 'age_max' => 120, 'min_val' => 13.0, 'max_val' => 150.0, 'display' => '13.0 - 150.0 ng/mL', 'interp' => 'Female range (< 13 indicates depleted iron stores)']
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // 3. SEROLOGY TESTS
            // =========================================================================
            [
                'category_code' => 'SERO',
                'test_code' => 'CRP',
                'test_name' => 'C-Reactive Protein (CRP Quantitative)',
                'short_name' => 'CRP Quantitative',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 350.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (2 Hours)',
                'method' => 'Latex Enhanced Immunoturbidimetry',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Sensitive acute-phase protein rising rapidly within 6-8h in acute bacterial infection, sepsis, tissue necrosis, and inflammatory flare-ups.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'C-Reactive Protein (CRP)', 'code' => 'CRP_VAL', 'unit' => 'mg/L', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 6.0, 'display' => '< 6.0 mg/L (Normal / Non-significant)', 'interp' => 'Values > 10 indicate active inflammatory response']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'RF',
                'test_name' => 'Rheumatoid Factor (RF / RA Test)',
                'short_name' => 'RA Factor',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 300.00,
                'cost_price' => 45.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Latex Slide Agglutination & Quantitative Immunoturbidimetry',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Autoimmune screening for Rheumatoid Arthritis and connective tissue disease.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Rheumatoid Factor Result', 'code' => 'RF_QUAL', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NEGATIVE', 'POSITIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NEGATIVE', 'interp' => 'Qualitative screen']]],
                    ['name' => 'RF Quantitative Titer', 'code' => 'RF_QUANT', 'unit' => 'IU/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 14.0, 'display' => '< 14.0 IU/mL (Negative)', 'interp' => 'Quantitative turbidimetric value']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'ASO',
                'test_name' => 'Anti-Streptolysin O (ASO / ASLO Titer)',
                'short_name' => 'ASO Titer',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 350.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Latex Agglutination & Quantitative Immunoturbidimetry',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Detects antibodies against streptolysin O, documenting antecedent Group A Beta-hemolytic Streptococcus infection in acute rheumatic fever and post-streptococcal glomerulonephritis.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'ASO Qualitative Result', 'code' => 'ASO_QUAL', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NEGATIVE', 'POSITIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NEGATIVE', 'interp' => 'Screen result']]],
                    ['name' => 'ASO Quantitative Titer', 'code' => 'ASO_QUANT', 'unit' => 'IU/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 200.0, 'display' => '< 200.0 IU/mL', 'interp' => 'Upper limit of normal for adults']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'TYPHOID-IGM',
                'test_name' => 'Typhoid IgM & IgG Rapid Antibody (Typhidot)',
                'short_name' => 'Typhidot',
                'specimen_type' => 'Plain Serum / Plasma',
                'department' => 'Serology',
                'price' => 350.00,
                'cost_price' => 60.00,
                'turnaround_time' => 'Same Day (1-2 Hours)',
                'method' => 'Immunochromatographic Rapid Test (ICT)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Early diagnostic detection of Salmonella typhi specific outer membrane protein (OMP) antibodies during the first week of enteric fever.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Typhoid IgM Antibody', 'code' => 'TYPHI_IGM', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Marker of acute infection']]],
                    ['name' => 'Typhoid IgG Antibody', 'code' => 'TYPHI_IGG', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Marker of past exposure / convalescence']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'WEIL-FELIX',
                'test_name' => 'Weil-Felix Agglutination Test',
                'short_name' => 'Weil-Felix',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 400.00,
                'cost_price' => 60.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Tube Agglutination using Proteus vulgaris (OX19, OX2) and Proteus mirabilis (OXK) Antigens',
                'instructions' => 'Plain serum. Pair acute and convalescent sera if possible.',
                'clinical_significance' => 'Heterophile agglutination screen for rickettsial infections (Epidemic/Endemic Typhus, Spotted Fever, and Scrub Typhus).',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Proteus OX19 Titer', 'code' => 'WF_OX19', 'unit' => 'Titer', 'type' => 'dropdown', 'dropdown' => "'< 1:20', '1:20', '1:40', '1:80', '1:160', '1:320', '1:640'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Titer < 1:80 (Non-significant)', 'interp' => 'Epidemic & Endemic Typhus group']]],
                    ['name' => 'Proteus OX2 Titer', 'code' => 'WF_OX2', 'unit' => 'Titer', 'type' => 'dropdown', 'dropdown' => "'< 1:20', '1:20', '1:40', '1:80', '1:160', '1:320', '1:640'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Titer < 1:80 (Non-significant)', 'interp' => 'Spotted Fever group']]],
                    ['name' => 'Proteus OXK Titer', 'code' => 'WF_OXK', 'unit' => 'Titer', 'type' => 'dropdown', 'dropdown' => "'< 1:20', '1:20', '1:40', '1:80', '1:160', '1:320', '1:640'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Titer < 1:80 (Non-significant)', 'interp' => 'Scrub Typhus (Tsutsugamushi)']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'VDRL',
                'test_name' => 'VDRL / RPR Syphilis Serology Screening',
                'short_name' => 'VDRL/RPR',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 200.00,
                'cost_price' => 30.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Flocculation / Carbon Particle Rapid Plasma Reagin (RPR)',
                'instructions' => 'Plain serum. Heat inactivate for VDRL or use untreated serum for RPR.',
                'clinical_significance' => 'Non-treponemal reagin screening for Treponema pallidum infection and therapeutic monitoring in syphilis. Reactive results require treponemal confirmation (TPHA / FTA-ABS).',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'VDRL / RPR Qualitative Result', 'code' => 'VDRL_QUAL', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE', 'WEAKLY REACTIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Standard non-reactive screen']]],
                    ['name' => 'VDRL / RPR Titer', 'code' => 'VDRL_TITER', 'unit' => 'Dilution', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'Not Applicable / Nil', 'interp' => 'Serial dilution endpoint reported when reactive']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'CHIKUNGUNYA',
                'test_name' => 'Chikungunya IgM Rapid Antibody',
                'short_name' => 'Chikungunya IgM',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 500.00,
                'cost_price' => 80.00,
                'turnaround_time' => 'Same Day (2 Hours)',
                'method' => 'Immunochromatographic Assay (ICT Rapid)',
                'instructions' => 'Plain serum. Collect on or after 5th day of acute febrile polyarthralgia.',
                'clinical_significance' => 'Diagnostic confirmation of acute Chikungunya virus infection causing disabling polyarthritis.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Chikungunya IgM Antibody', 'code' => 'CHIK_IGM', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative)', 'REACTIVE (Positive)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Absence of specific IgM antibody']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'SCRUB-TYPHUS',
                'test_name' => 'Scrub Typhus IgM Antibody (Orientia tsutsugamushi)',
                'short_name' => 'Scrub Typhus IgM',
                'specimen_type' => 'Plain Serum',
                'department' => 'Serology',
                'price' => 600.00,
                'cost_price' => 90.00,
                'turnaround_time' => 'Same Day (2-3 Hours)',
                'method' => 'Immunochromatography Rapid / ELISA',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Confirmation of acute chigger-borne Orientia tsutsugamushi infection presenting as acute febrile illness with or without eschar.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Scrub Typhus IgM Antibody', 'code' => 'SCRUB_IGM', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative)', 'REACTIVE (Positive)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Absence of specific Orientia IgM']]]
                ]
            ],

            // =========================================================================
            // 4. IMMUNOLOGY TESTS
            // =========================================================================
            [
                'category_code' => 'ENDO',
                'test_code' => 'FREE-THYROID',
                'test_name' => 'Free Thyroid Profile (FT3, FT4, TSH Ultrasensitive)',
                'short_name' => 'Free TFT',
                'specimen_type' => 'Plain Serum',
                'department' => 'Endocrinology & Immunoassays',
                'price' => 950.00,
                'cost_price' => 140.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Direct Chemiluminescence Immunoassay (CLIA / CMIA)',
                'instructions' => 'Morning fasting serum preferred. Biotin supplements should be discontinued 48h prior.',
                'clinical_significance' => 'Biologically active free hormone fractions unaffected by alterations in thyroid-binding globulin (TBG) concentrations (pregnancy, oral contraceptives).',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Free Triiodothyronine (FT3)', 'code' => 'FT3_VAL', 'unit' => 'pg/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 2.3, 'max_val' => 4.2, 'display' => '2.3 - 4.2 pg/mL', 'interp' => 'Unbound biologically active T3']]],
                    ['name' => 'Free Thyroxine (FT4)', 'code' => 'FT4_VAL', 'unit' => 'ng/dL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.89, 'max_val' => 1.76, 'display' => '0.89 - 1.76 ng/dL', 'interp' => 'Unbound biologically active T4']]],
                    ['name' => 'TSH Ultrasensitive (3rd Generation)', 'code' => 'FT_TSH', 'unit' => 'µIU/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.35, 'max_val' => 4.94, 'display' => '0.350 - 4.940 µIU/mL', 'interp' => 'Pituitary thyroid axis feedback']]]
                ]
            ],
            [
                'category_code' => 'ENDO',
                'test_code' => 'TSH-STANDALONE',
                'test_name' => 'Thyroid Stimulating Hormone (TSH Ultrasensitive Standalone)',
                'short_name' => 'TSH Standalone',
                'specimen_type' => 'Plain Serum',
                'department' => 'Endocrinology & Immunoassays',
                'price' => 300.00,
                'cost_price' => 40.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => '3rd Generation Ultrasensitive Chemiluminescence (CLIA / ECLIA)',
                'instructions' => 'Morning collection preferred. Withhold levothyroxine morning dose until after phlebotomy.',
                'clinical_significance' => 'Primary first-line screening investigation for thyroid dysfunction and monitoring of levothyroxine replacement therapy.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Thyroid Stimulating Hormone (TSH)', 'code' => 'TSH_STANDALONE_VAL', 'unit' => 'µIU/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.35, 'max_val' => 4.94, 'display' => '0.350 - 4.940 µIU/mL (Pregnancy 1st Trimester: 0.1 - 2.5)', 'interp' => 'Pituitary thyrotropic regulation']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'ANTI-TPO',
                'test_name' => 'Anti-Thyroid Peroxidase Antibody (Anti-TPO / Microsomal)',
                'short_name' => 'Anti-TPO',
                'specimen_type' => 'Plain Serum',
                'department' => 'Immunology & Autoimmunity',
                'price' => 850.00,
                'cost_price' => 120.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Chemiluminescence Immunoassay (CLIA)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Diagnostic hallmark of autoimmune thyroiditis (Hashimoto disease, atrophic thyroiditis) and Graves disease.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Anti-TPO Antibody Titer', 'code' => 'TPO_TITER', 'unit' => 'IU/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 34.0, 'display' => '< 34.0 IU/mL (Negative)', 'interp' => 'Values ≥ 34 IU/mL indicate autoimmune thyroiditis']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'ANTI-TG',
                'test_name' => 'Anti-Thyroglobulin Antibody (Anti-Tg)',
                'short_name' => 'Anti-Tg',
                'specimen_type' => 'Plain Serum',
                'department' => 'Immunology & Autoimmunity',
                'price' => 850.00,
                'cost_price' => 120.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Chemiluminescence Immunoassay (CLIA)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Investigation of thyroid autoimmunity and essential baseline prior to monitoring serum thyroglobulin in differentiated thyroid carcinoma.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Anti-Thyroglobulin Antibody Titer', 'code' => 'TG_TITER', 'unit' => 'IU/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 115.0, 'display' => '< 115.0 IU/mL (Negative)', 'interp' => 'Absence of thyroglobulin autoantibodies']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'VIT-B12',
                'test_name' => 'Vitamin B12 (Cyanocobalamin)',
                'short_name' => 'Vitamin B12',
                'specimen_type' => 'Plain Serum (Light Protected)',
                'department' => 'Clinical Biochemistry / Immunoassays',
                'price' => 850.00,
                'cost_price' => 130.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Competitive Chemiluminescent Immunoassay (CLIA / ECLIA)',
                'instructions' => 'Fasting plain serum. Protect sample from light during transport.',
                'clinical_significance' => 'Differential diagnosis of macrocytic megaloblastic anemia, subacute combined degeneration of cord, peripheral neuropathy, and cognitive decline.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Vitamin B12 Level', 'code' => 'B12_VAL', 'unit' => 'pg/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 211.0, 'max_val' => 911.0, 'display' => '211 - 911 pg/mL (Deficient: < 150, Borderline: 150-210)', 'interp' => 'Serum cobalamin stores']]]
                ]
            ],
            [
                'category_code' => 'BIO',
                'test_code' => 'VIT-D',
                'test_name' => 'Vitamin D Total 25-Hydroxy (25-OH Vitamin D)',
                'short_name' => 'Vitamin D',
                'specimen_type' => 'Plain Serum',
                'department' => 'Clinical Biochemistry / Immunoassays',
                'price' => 1100.00,
                'cost_price' => 160.00,
                'turnaround_time' => 'Same Day (4 Hours)',
                'method' => 'Direct Chemiluminescence Immunoassay (CLIA / LC-MS)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Gold standard indicator of total body vitamin D nutritional status (D2 + D3). Evaluates osteomalacia, osteoporosis, and immune health.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => '25-OH Vitamin D Total', 'code' => 'VITD_VAL', 'unit' => 'ng/mL', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 30.0, 'max_val' => 100.0, 'display' => '30.0 - 100.0 ng/mL (Sufficient) | < 20 Deficient, 20-29 Insufficient, > 100 Toxicity', 'interp' => 'Endocrine Society Clinical Guidelines']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'IGE-TOTAL',
                'test_name' => 'Total Immunoglobulin E (Serum Total IgE)',
                'short_name' => 'Total IgE',
                'specimen_type' => 'Plain Serum',
                'department' => 'Immunology',
                'price' => 650.00,
                'cost_price' => 95.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => 'Chemiluminescent Immunoassay (CLIA)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Elevated in atopic disorders (allergic asthma, atopic dermatitis, allergic rhinitis), bronchopulmonary aspergillosis, and helminthic parasite infestations.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Total IgE Level', 'code' => 'IGE_VAL', 'unit' => 'kU/L', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 100.0, 'display' => '< 100.0 kU/L (Adults)', 'interp' => 'Atopic threshold']]]
                ]
            ],
            [
                'category_code' => 'SERO',
                'test_code' => 'ANA',
                'test_name' => 'Antinuclear Antibodies (ANA / FANA by IIF / EIA)',
                'short_name' => 'ANA Screen',
                'specimen_type' => 'Plain Serum',
                'department' => 'Immunology & Autoimmunity',
                'price' => 950.00,
                'cost_price' => 150.00,
                'turnaround_time' => 'Same Day (6 Hours / Next Day)',
                'method' => 'Indirect Immunofluorescence (Hep-2 IIF) & ELISA Recombinant Antigens',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'First-line screening test for systemic autoimmune connective tissue diseases, primarily Systemic Lupus Erythematosus (SLE > 95% sensitivity), systemic sclerosis, and Sjogren syndrome.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'ANA Screening Result', 'code' => 'ANA_SCREEN', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NEGATIVE', 'POSITIVE', 'BORDERLINE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NEGATIVE', 'interp' => 'Primary qualitative screen']]],
                    ['name' => 'ANA Endpoint Titer', 'code' => 'ANA_TITER', 'unit' => 'Titer', 'type' => 'text', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => '< 1:80 (Negative)', 'interp' => 'Titers ≥ 1:160 are clinically significant']]],
                    ['name' => 'Fluorescence Staining Pattern', 'code' => 'ANA_PATTERN', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'No Pattern Observed (Negative)', 'Homogeneous Pattern', 'Speckled Pattern', 'Nucleolar Pattern', 'Centromere Pattern', 'Cytoplasmic Pattern'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'No Pattern (Negative)', 'interp' => 'ICAP classification of IIF staining']]]
                ]
            ],

            // =========================================================================
            // 5. VIRAL MARKERS TESTS (Confidential & Method-Differentiated)
            // =========================================================================
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HBSAG-RAPID',
                'test_name' => 'Hepatitis B Surface Antigen (HBsAg Rapid Screening)',
                'short_name' => 'HBsAg Rapid',
                'specimen_type' => 'Plain Serum / Plasma',
                'department' => 'Viral Markers & Serology',
                'price' => 200.00,
                'cost_price' => 30.00,
                'turnaround_time' => 'Same Day (1-2 Hours)',
                'method' => 'Immunochromatographic Rapid Lateral Flow (ICT)',
                'instructions' => 'Plain serum. Follow strict biohazard precautions.',
                'clinical_significance' => 'Routine point-of-care qualitative screening for Hepatitis B surface antigen. Reactive results must be confirmed by quantitative CLIA/ELISA.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'HBsAg Screening Result', 'code' => 'HBSAG_RAPID_RES', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative)', 'REACTIVE (Positive)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Absence of detectable surface antigen']]]
                ]
            ],
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HBSAG-CLIA',
                'test_name' => 'HBsAg Quantitative / Confirmatory Assay (ECLIA / CLIA)',
                'short_name' => 'HBsAg CLIA',
                'specimen_type' => 'Plain Serum',
                'department' => 'Viral Markers & Immunoassays',
                'price' => 550.00,
                'cost_price' => 80.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => 'Electrochemiluminescence Immunoassay (ECLIA / CMIA)',
                'instructions' => 'Plain serum. Confidential test handling.',
                'clinical_significance' => 'High-sensitivity quantitative confirmatory assay for Hepatitis B infection, reporting numeric Cutoff Index (COI / S/CO).',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'HBsAg Index (S/CO)', 'code' => 'HBSAG_INDEX', 'unit' => 'Index', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 0.89, 'display' => '< 0.90 Non-reactive (0.90-0.99 Greyzone, ≥ 1.00 Reactive)', 'interp' => 'Sample-to-Cutoff ratio']]],
                    ['name' => 'HBsAg Interpretation', 'code' => 'HBSAG_INTERP', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE', 'BORDERLINE / GREYZONE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'Confirmatory medical conclusion']]]
                ]
            ],
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HCV-RAPID',
                'test_name' => 'HCV Antibody Rapid Screening Test (Anti-HCV)',
                'short_name' => 'Anti-HCV Rapid',
                'specimen_type' => 'Plain Serum / Plasma',
                'department' => 'Viral Markers & Serology',
                'price' => 350.00,
                'cost_price' => 50.00,
                'turnaround_time' => 'Same Day (1-2 Hours)',
                'method' => 'Immunochromatographic Rapid Test (ICT)',
                'instructions' => 'Plain serum. Universal precautions mandatory.',
                'clinical_significance' => 'Qualitative screening for antibodies directed against Hepatitis C virus. Positive results warrant HCV-RNA PCR quantification.',
                'is_package' => 0,
                'parameters' => [
                    ['name' => 'Anti-HCV Antibody Screening Result', 'code' => 'HCV_RAPID_RES', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative)', 'REACTIVE (Positive)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE (Negative)', 'interp' => 'Absence of HCV antibodies']]]
                ]
            ],
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HIV-RAPID',
                'test_name' => 'HIV 1 & 2 Rapid Screening Test (3rd Gen Antibody / Tri-dot)',
                'short_name' => 'HIV 1/2 Rapid',
                'specimen_type' => 'Plain Serum / Plasma',
                'department' => 'Viral Markers & ICTC',
                'price' => 350.00,
                'cost_price' => 55.00,
                'turnaround_time' => 'Same Day (1-2 Hours)',
                'method' => 'Immunofiltration / Immunochromatography (NACO / WHO Approved Rapid 3-Test Protocol)',
                'instructions' => 'Strict confidentiality guaranteed. Pre-test and post-test counseling recommended. Handle all samples with universal biohazard precautions.',
                'clinical_significance' => 'Qualitative screening for antibodies against HIV-1 and HIV-2. A reactive screening test requires confirmatory testing according to the National AIDS Control Organization (NACO) algorithm before diagnostic release.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'HIV-1 Antibody Result', 'code' => 'HIV1_RES', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'HIV-1 envelope glycoprotein specific']]],
                    ['name' => 'HIV-2 Antibody Result', 'code' => 'HIV2_RES', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'HIV-2 specific']]],
                    ['name' => 'Screening Conclusion', 'code' => 'HIV_CONCLUSION', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE for HIV-1 and HIV-2 Antibodies', 'REACTIVE on Screening (Advise NACO Confirmation)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE for HIV-1 and HIV-2 Antibodies', 'interp' => 'Confidential clinical conclusion']]]
                ]
            ],
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HIV-COMBO-4TH',
                'test_name' => 'HIV 1 & 2 4th Gen Antigen/Antibody Combination Assay (p24 Ag + Ab)',
                'short_name' => 'HIV Duo 4th Gen',
                'specimen_type' => 'Plain Serum',
                'department' => 'Viral Markers & Reference Immunoassays',
                'price' => 850.00,
                'cost_price' => 130.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => '4th Generation Chemiluminescence Immunoassay (CLIA / CMIA Duo)',
                'instructions' => 'Plain serum. Strict biohazard and patient confidentiality protocol.',
                'clinical_significance' => 'Advanced 4th generation assay detecting both HIV-1 p24 antigen and HIV-1/2 antibodies simultaneously, narrowing the diagnostic window period to 14 days.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'HIV-1 p24 Antigen & Antibodies (Duo Index)', 'code' => 'HIV_DUO_INDEX', 'unit' => 'S/CO', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 0.89, 'display' => '< 0.90 Non-reactive (0.90-0.99 Greyzone, ≥ 1.00 Reactive)', 'interp' => '4th Generation cut-off index']]],
                    ['name' => '4th Generation Clinical Impression', 'code' => 'HIV_DUO_IMPR', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE for HIV-1 p24 Antigen & HIV-1/2 Antibodies', 'REACTIVE for HIV-1 p24 Antigen and/or HIV-1/2 Antibodies'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'Window-period reduced screening']]]
                ]
            ],
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HAV-IGM',
                'test_name' => 'Hepatitis A Virus IgM Antibody (Anti-HAV IgM)',
                'short_name' => 'HAV IgM',
                'specimen_type' => 'Plain Serum',
                'department' => 'Viral Markers',
                'price' => 750.00,
                'cost_price' => 110.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => 'Chemiluminescent Immunoassay (CLIA / ELISA)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Diagnostic hallmark of acute Hepatitis A viral infection (fecal-oral transmission). Appears during acute jaundice and persists for 3-6 months.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Anti-HAV IgM Index (S/CO)', 'code' => 'HAV_INDEX', 'unit' => 'Index', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 0.79, 'display' => '< 0.80 Non-reactive (0.80-1.20 Borderline, > 1.20 Reactive)', 'interp' => 'Chemiluminescent cut-off index']]],
                    ['name' => 'Anti-HAV IgM Interpretation', 'code' => 'HAV_INTERP', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE (Negative for acute Hepatitis A)', 'REACTIVE (Consistent with acute Hepatitis A infection)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'Acute enterically transmitted hepatitis marker']]]
                ]
            ],
            [
                'category_code' => 'VIRAL',
                'test_code' => 'HBCORE-IGM',
                'test_name' => 'Hepatitis B Core IgM Antibody (Anti-HBc IgM)',
                'short_name' => 'Anti-HBc IgM',
                'specimen_type' => 'Plain Serum',
                'department' => 'Viral Markers',
                'price' => 800.00,
                'cost_price' => 120.00,
                'turnaround_time' => 'Same Day (3-4 Hours)',
                'method' => 'Chemiluminescent Immunoassay (CLIA / CMIA)',
                'instructions' => 'Plain serum.',
                'clinical_significance' => 'Critical marker distinguishing acute from chronic HBV infection. Sole diagnostic serological marker during the "window period" between HBsAg disappearance and anti-HBs appearance.',
                'is_package' => 1,
                'parameters' => [
                    ['name' => 'Anti-HBc IgM Index', 'code' => 'HBCORE_INDEX', 'unit' => 'Index', 'type' => 'numeric', 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'min_val' => 0.0, 'max_val' => 0.99, 'display' => '< 1.00 Non-reactive (≥ 1.00 Reactive for acute HBV)', 'interp' => 'Index ratio']]],
                    ['name' => 'Anti-HBc IgM Interpretation', 'code' => 'HBCORE_INTERP', 'unit' => '', 'type' => 'dropdown', 'dropdown' => "'NON-REACTIVE', 'REACTIVE (Indicates recent/acute HBV infection or acute flare)'", 'intervals' => [['gender' => 'All', 'age_min' => 0, 'age_max' => 120, 'display' => 'NON-REACTIVE', 'interp' => 'Window period diagnostic marker']]]
                ]
            ]
        ];
    }

    /**
     * Run Additive Import with Duplicate Protection and DB Transaction
     */
    public function executeSafeImport(): array
    {
        $categoryMap = $this->ensureCategories();
        $candidates = $this->getCandidateCatalog();

        // Load existing test codes and names to prevent duplicates
        $existingRows = $this->pdo->query("SELECT id, test_code, test_name FROM `tests`")->fetchAll(PDO::FETCH_ASSOC);
        $existingCodes = [];
        $existingNames = [];
        foreach ($existingRows as $row) {
            $existingCodes[strtoupper(trim($row['test_code']))] = (int)$row['id'];
            $existingNames[self::normalize($row['test_name'])] = (int)$row['id'];
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($candidates as $cand) {
                $code = strtoupper(trim($cand['test_code']));
                $normName = self::normalize($cand['test_name']);

                // 1. Strict Duplicate Check (Code or Name)
                if (isset($existingCodes[$code])) {
                    $existingId = $existingCodes[$code];
                    $this->skipped[] = [
                        'code' => $code,
                        'name' => $cand['test_name'],
                        'reason' => "Test code '{$code}' already exists in database (ID: #{$existingId}). Overwrite protected."
                    ];
                    continue;
                }

                if (isset($existingNames[$normName])) {
                    $existingId = $existingNames[$normName];
                    $this->skipped[] = [
                        'code' => $code,
                        'name' => $cand['test_name'],
                        'reason' => "Test with normalized name '{$cand['test_name']}' already exists (ID: #{$existingId}). Overwrite protected."
                    ];
                    continue;
                }

                // 2. Resolve Category ID
                $catCode = strtoupper($cand['category_code']);
                $categoryId = $categoryMap[$catCode] ?? $categoryMap['BIO'];

                // 3. Insert Test Master Record
                $testStmt = $this->pdo->prepare("
                    INSERT INTO `tests` (
                        `category_id`, `test_code`, `test_name`, `short_name`, `specimen_type`,
                        `department`, `price`, `cost_price`, `turnaround_time`, `method`,
                        `instructions`, `clinical_significance`, `is_package`, `is_active`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");

                $testStmt->execute([
                    $categoryId,
                    $code,
                    $cand['test_name'],
                    $cand['short_name'] ?? null,
                    $cand['specimen_type'],
                    $cand['department'],
                    $cand['price'],
                    $cand['cost_price'],
                    $cand['turnaround_time'],
                    $cand['method'],
                    $cand['instructions'],
                    $cand['clinical_significance'],
                    $cand['is_package']
                ]);

                $testId = (int)$this->pdo->lastInsertId();
                $existingCodes[$code] = $testId;
                $existingNames[$normName] = $testId;

                $paramCount = 0;
                $intervalCount = 0;

                // 4. Insert Parameters & Reference Intervals
                foreach ($cand['parameters'] as $pIdx => $p) {
                    $paramStmt = $this->pdo->prepare("
                        INSERT INTO `test_parameters` (
                            `test_id`, `parameter_name`, `parameter_code`, `unit`,
                            `result_type`, `dropdown_options`, `critical_low`, `critical_high`, `sort_order`
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $paramStmt->execute([
                        $testId,
                        $p['name'],
                        $p['code'],
                        $p['unit'] ?? null,
                        $p['type'] ?? 'numeric',
                        $p['dropdown'] ?? null,
                        $p['crit_low'] ?? null,
                        $p['crit_high'] ?? null,
                        $pIdx + 1
                    ]);

                    $paramId = (int)$this->pdo->lastInsertId();
                    $paramCount++;

                    // Reference intervals
                    if (!empty($p['intervals'])) {
                        foreach ($p['intervals'] as $int) {
                            $intStmt = $this->pdo->prepare("
                                INSERT INTO `reference_intervals` (
                                    `parameter_id`, `gender`, `age_min`, `age_max`, `age_unit`,
                                    `min_val`, `max_val`, `display_interval`, `interpretation`
                                ) VALUES (?, ?, ?, ?, 'Years', ?, ?, ?, ?)
                            ");

                            $intStmt->execute([
                                $paramId,
                                $int['gender'] ?? 'All',
                                $int['age_min'] ?? 0,
                                $int['age_max'] ?? 120,
                                $int['min_val'] ?? null,
                                $int['max_val'] ?? null,
                                $int['display'],
                                $int['interp'] ?? null
                            ]);
                            $intervalCount++;
                        }
                    }
                }

                $this->inserted[] = [
                    'id' => $testId,
                    'code' => $code,
                    'name' => $cand['test_name'],
                    'category_id' => $categoryId,
                    'parameters' => $paramCount,
                    'intervals' => $intervalCount
                ];
            }

            // Commit the transaction
            $this->pdo->commit();

            // Log action to audit_logs
            if (function_exists('log_audit')) {
                log_audit(
                    'Test Master Safe Expansion',
                    'Tests',
                    'BATCH_EXPANSION',
                    "Successfully added " . count($this->inserted) . " new pathology tests, skipped " . count($this->skipped) . " duplicates."
                );
            }

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            $this->errors[] = $e->getMessage();
            throw $e;
        }

        return [
            'success' => empty($this->errors),
            'total_candidates' => count($candidates),
            'inserted_count' => count($this->inserted),
            'skipped_count' => count($this->skipped),
            'inserted' => $this->inserted,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'logs' => $this->logs
        ];
    }
}
