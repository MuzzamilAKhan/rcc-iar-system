<?php

namespace App\Services\Ccr;

/** Starter broad screening prompts from the client's 16-area specification.
 * Detailed controlled company-specific questionnaires are a later release.
 */
final class AssessmentQuestionCatalog
{
    public const QUESTIONS = [
        'design' => 'Could the change affect design, specifications, functionality, safety, performance, intended use or user needs?',
        'materials' => 'Could the change affect materials, composition, biocompatibility, biological safety or patient-contacting parts?',
        'manufacturing' => 'Could the change affect manufacturing process, equipment, tooling, facilities, controls or validation?',
        'sterilisation' => 'Could the change affect sterilisation method, cycle, bioburden, validation or sterile barrier requirements?',
        'packaging' => 'Could the change affect packaging design, materials, sealing, integrity or validation?',
        'shelf_life' => 'Could the change affect shelf life, storage conditions, stability or ageing evidence?',
        'clinical' => 'Could the change affect clinical safety, performance, claims, patient population or evidence?',
        'risk' => 'Could the change introduce or change hazards, risks, risk controls or benefit-risk conclusions?',
        'software' => 'Could the change affect software, firmware, algorithms, interfaces, cybersecurity or validation?',
        'labelling' => 'Could the change affect labels, IFUs, warnings, artwork, claims, translations or UDI?',
        'master_data' => 'Could the change require updates to ERP, SAP, BOM, specifications or controlled master data?',
        'regulatory' => 'Could the change affect classification, registrations, certificates, submissions, authorisations or regulatory documents?',
        'supply_chain' => 'Could the change affect supply continuity, inventory, logistics, distribution or stop-ship decisions?',
        'commercial' => 'Could the change affect commercial claims, market availability or customer commitments?',
        'quality' => 'Are QMS controls, documentation, testing, training, validation and approval requirements affected?',
        'supplier_quality' => 'Could the change require supplier qualification, quality agreement review, audit or incoming inspection?',
    ];
}
