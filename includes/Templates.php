<?php

declare(strict_types=1);

namespace SepoForms;

defined('ABSPATH') || exit;

/**
 * Starter forms offered when a new form is created. Each is written in the
 * text syntax FormDefinition::fromText() reads, so the same text can be
 * pasted into "Import from text" on any site.
 */
final class Templates
{
    /** @return array<string,array{label:string,description:string,text:string}> */
    public static function all(): array
    {
        $t = [
            'contact' => [
                'label'       => __('Contact enquiry', 'sepo-forms'),
                'description' => __('Name, email, topic and message. A good first form for any site.', 'sepo-forms'),
                'text'        => "title: Contact us\ndescription: Send us a message and we will reply within two working days.\nsuccessMessage: Thank you — we have your message and will be in touch soon.\nsubmitButtonText: Send message\n\ngroup1: Your details\nquestion1: Full name\nquestionRequired1: true\nquestion2: Email address\nquestionType2: email\nquestionRequired2: true\nquestion3: Phone number\nquestionType3: phone\n\ngroup2: Your message\nquestion4: What is it about?\nquestionType4: multipleChoice\nquestionOptions4: General question | Sales | Support | Partnership | Other\nquestionRequired4: true\nquestion5: Message\nquestionType5: longText\nquestionRequired5: true\nquestionPlaceholder5: How can we help?\n",
            ],
            'course_feedback' => [
                'label'       => __('Course feedback (education)', 'sepo-forms'),
                'description' => __('End-of-course survey with ratings, what worked and what to improve.', 'sepo-forms'),
                'text'        => "title: Course feedback\ndescription: Help us improve this course. It takes about three minutes.\nallowMultipleSubmissions: false\nrequireLogin: true\n\ngroup1: About the course\nquestion1: Which course did you take?\nquestionRequired1: true\nquestion2: How would you rate the course overall?\nquestionType2: multipleChoice\nquestionOptions2: Excellent | Good | Fair | Poor\nquestionRequired2: true\nquestion3: How many hours a week did you spend on it?\nquestionType3: numeric\n\ngroup2: Teaching\ngroupHelp2: Please indicate how much you agree with each statement.\nquestion4: The lessons were clear and well organised.\nquestionType4: multipleChoice\nquestionOptions4: Strongly agree | Agree | Neutral | Disagree | Strongly disagree\nquestion5: The assessments matched what was taught.\nquestionType5: multipleChoice\nquestionOptions5: Strongly agree | Agree | Neutral | Disagree | Strongly disagree\nquestion6: Which parts were most useful?\nquestionType6: multipleSelect\nquestionOptions6: Video lessons | Readings | Quizzes | Live sessions | Discussion forum\n\ngroup3: Your thoughts\nquestion7: What should we keep?\nquestionType7: longText\nquestion8: What should we change?\nquestionType8: longText\n",
            ],
            'expense_claim' => [
                'label'       => __('Expense claim (finance)', 'sepo-forms'),
                'description' => __('Staff expense claim with amount, date, category and a receipt upload.', 'sepo-forms'),
                'text'        => "title: Expense claim\ndescription: Claim back money you spent on behalf of the organisation. Attach a receipt for every claim.\nrequireLogin: true\nvisibility: private\nupload: true\nuploadTitle: Receipts\nuploadDescription: A photo or PDF of each receipt.\nuploadMaxSizeMB: 5\nuploadTopics: receipt:Receipt:Required | other:Other supporting document\n\ngroup1: The expense\nquestion1: What did you buy?\nquestionRequired1: true\nquestion2: Amount\nquestionType2: numeric\nquestionRequired2: true\nquestionHelp2: In your local currency, without the currency sign.\nquestion3: Date of purchase\nquestionType3: date\nquestionRequired3: true\nquestion4: Category\nquestionType4: multipleChoice\nquestionOptions4: Travel | Meals | Supplies | Training | Other\nquestionRequired4: true\n\ngroup2: Payment\nquestion5: Cost centre or project\nquestion6: Notes for the finance team\nquestionType6: longText\n",
            ],
            'job_application' => [
                'label'       => __('Job application (business)', 'sepo-forms'),
                'description' => __('Applicant details, experience and CV upload.', 'sepo-forms'),
                'text'        => "title: Job application\ndescription: Tell us about yourself and attach your CV.\ncollectEmail: true\nupload: true\nuploadTitle: Your documents\nuploadMaxSizeMB: 8\nuploadTopics: cv:CV:Required | cover:Cover letter\n\ngroup1: About you\nquestion1: Full name\nquestionRequired1: true\nquestion2: Phone number\nquestionType2: phone\nquestionRequired2: true\nquestion3: LinkedIn or portfolio\nquestionType3: url\n\ngroup2: The role\nquestion4: Which role are you applying for?\nquestionRequired4: true\nquestion5: Years of relevant experience\nquestionType5: numeric\nquestion6: Earliest start date\nquestionType6: date\nquestion7: Why do you want this role?\nquestionType7: longText\nquestionRequired7: true\n",
            ],
            'team_registration' => [
                'label'       => __('Team registration (sports)', 'sepo-forms'),
                'description' => __('Player sign-up with position, emergency contact and consent.', 'sepo-forms'),
                'text'        => "title: Season registration\ndescription: Register for the coming season. One form per player.\n\ngroup1: Player\nquestion1: Player's full name\nquestionRequired1: true\nquestion2: Date of birth\nquestionType2: date\nquestionRequired2: true\nquestion3: Preferred positions\nquestionType3: multipleSelect\nquestionOptions3: Goalkeeper | Defender | Midfielder | Forward | Any\nquestion4: Team\nquestionType4: multipleChoice\nquestionOptions4: Under 12 | Under 15 | Under 18 | Seniors | Veterans\nquestionRequired4: true\n\ngroup2: Emergency contact\ngroupHelp2: Someone we can call during training or matches.\nquestion5: Contact name\nquestionRequired5: true\nquestion6: Contact phone\nquestionType6: phone\nquestionRequired6: true\nquestion7: Medical information the coaches should know\nquestionType7: longText\n\ngroup3: Consent\nquestion8: I agree to the club's code of conduct\nquestionType8: multipleChoice\nquestionOptions8: I agree\nquestionRequired8: true\n",
            ],
            'visitor_card' => [
                'label'       => __('Visitor card (church)', 'sepo-forms'),
                'description' => __('Welcome card for first-time visitors with interests and prayer requests.', 'sepo-forms'),
                'text'        => "title: Welcome — we're glad you're here\ndescription: Please fill in this card so we can get to know you.\nsuccessMessage: Thank you for visiting. Someone from our welcome team will be in touch.\n\ngroup1: About you\nquestion1: Your name\nquestionRequired1: true\nquestion2: Email\nquestionType2: email\nquestion3: Phone\nquestionType3: phone\nquestion4: Is this your first visit?\nquestionType4: multipleChoice\nquestionOptions4: Yes, my first time | I've visited a few times | I attend regularly\n\ngroup2: Getting connected\nquestion5: I'd like to know more about\nquestionType5: multipleSelect\nquestionOptions5: Small groups | Youth ministry | Children's ministry | Volunteering | Membership\nquestion6: Prayer request\nquestionType6: longText\nquestionHelp6: Shared only with the pastoral team.\n",
            ],
            'event_rsvp' => [
                'label'       => __('Event RSVP', 'sepo-forms'),
                'description' => __('Attendance, number of guests and dietary needs.', 'sepo-forms'),
                'text'        => "title: Event RSVP\ndescription: Let us know if you can make it.\ncollectEmail: true\n\nquestion1: Your name\nquestionRequired1: true\nquestion2: Will you attend?\nquestionType2: multipleChoice\nquestionOptions2: Yes | No | Maybe\nquestionRequired2: true\nquestion3: Number of guests (including you)\nquestionType3: numeric\nquestion4: Dietary requirements\nquestionType4: multipleSelect\nquestionOptions4: Vegetarian | Vegan | Gluten free | Halal | None\n",
            ],
        ];
        /** Add or change the starter forms. */
        return (array) apply_filters('sepo_forms_templates', $t);
    }

    /**
     * The templates parsed and ready for the builder.
     *
     * @return list<array{id:string,label:string,description:string,form:array<string,mixed>}>
     */
    public static function forBuilder(): array
    {
        $out = [];
        foreach (self::all() as $id => $t) {
            $out[] = ['id' => (string) $id, 'label' => $t['label'], 'description' => $t['description'], 'form' => FormDefinition::fromText($t['text'])];
        }
        return $out;
    }
}
