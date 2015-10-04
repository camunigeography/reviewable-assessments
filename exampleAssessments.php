<?php

# Class implementing an example assessment system
require_once ('reviewable-assessments/reviewableAssessments.php');
class exampleAssessments extends reviewableAssessments
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Add implementation defaults
		$defaults = array (
			'applicationName'		=> 'Example assessments',
			'database'				=> 'exampleassessments',
			'description'			=> 'example assessment',
			'descriptionPlural'		=> 'example assessments',
			'directorDescription'	=> 'Example Assessments Officer',
		);
		
		# Merge in the default defaults
		$defaults += parent::defaults ();
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Database structure
	public function databaseStructureSpecificFields ()
	{
		# Return the SQL
		return $sql = "
			  /* Domain-specific fields to be added here, if any */
			  
		";
	}
	
	
	# Submission form
	public function submissionForm ($data)
	{
		return parent::submissionForm ($data);
	}
	
	
	# Function to define the asssessment form template
	public function formTemplateLocal ($data, $watermark)
	{
		$html  = '<h3>Section B &#8211; Questionnaire</h3>';
		$html .= $watermark;
		
		
		# Final confirmation
		$html .= '
			<h3 class="pagebreak">Confirmation</h3>
			<div class="graybox">
				<p>All issues, however trivial they may seem, should be reported. I agree that if an incident occurs during the matters covered by this assessment, I will report this.</p>
				<p><strong>Tick to confirm: {confirmation}</strong></p>
			</div>
		';
		
		# Return the HTML
		return $html;
	}
}

?>