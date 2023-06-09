<?php
/**
 * Tracking and store payment error logs.
 *
 * @package ubc-dpp
 * @since 0.1.9
 */

namespace UBC\CTLT\DPP;

/**
 * Payment error logs
 */
class Payment_Logs {

	/**
	 * Payment logs init.
	 */
	public static function init() {
		add_filter( 'gform_settings_menu', array( __CLASS__, 'add_payment_log_menu' ) );
		add_action( 'gform_settings_epayment_logs', array( __CLASS__, 'add_payment_log_menu_content' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'create_post_type_and_taxonomies' ) );
	}//end init()

	/**
	 * Create Logs custom post type and register taxonomy.
	 */
	public static function create_post_type_and_taxonomies() {
		register_post_type(
			'ubc_dpp_logs',
			array(
				'labels'       => array(
					'name'          => __( 'E-Payment Logs', 'ubc-dpp' ),
					'singular_name' => __( 'E-Payment Log', 'ubc-dpp' ),
				),
				'public'       => true,
				'capabilities' => array( 'manage_network' )
			)
		);

		register_taxonomy(
			'ubc_dpp_logs_category',
			array( 'ubc_dpp_logs' ),
			array(
				'labels'       => array(
					'name'              => _x( 'Categoris', 'taxonomy general name', 'ubc-dpp' ),
					'singular_name'     => _x( 'Category', 'taxonomy singular name', 'ubc-dpp' ),
					'search_items'      => __( 'Search Categoris', 'ubc-dpp' ),
					'all_items'         => __( 'All Categoris', 'ubc-dpp' ),
					'parent_item'       => __( 'Parent Category', 'ubc-dpp' ),
					'parent_item_colon' => __( 'Parent Category:', 'ubc-dpp' ),
					'edit_item'         => __( 'Edit Category', 'ubc-dpp' ),
					'update_item'       => __( 'Update Category', 'ubc-dpp' ),
					'add_new_item'      => __( 'Add New Category', 'ubc-dpp' ),
					'new_item_name'     => __( 'New Category Name', 'ubc-dpp' ),
					'menu_name'         => __( 'Category', 'ubc-dpp' ),
				),
				'public'       => true,
				'hierarchical' => false,
			)
		);

	}//end create_post_type_and_taxonomies()

	/**
	 * Add Logs tab in the Gravity Forms settings page.
	 */
	public static function add_payment_log_menu( $tabs ) {
		if ( ! is_multisite() && ! current_user_can( 'manage_options' ) ) {
			return $tabs;
		}

		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			return $tabs;
		}

		$tabs[] = array( 'name' => 'epayment_logs', 'label' => 'E-Payment Logs' );
		return $tabs;
	}//end add_payment_log_menu();

	/**
	 * Add content for the Gravity Forms settings page Logs tab.
	 */
	public static function add_payment_log_menu_content( $subview ) {
		if ( ! is_multisite() && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$categories = get_terms( array(
			'taxonomy'   => 'ubc_dpp_logs_category',
			'hide_empty' => false,
		) );

		if ( ! is_array( $categories ) || 0 === count( $categories ) ) {
			return;
		}

		ob_start();
		?>
			<ul class="nav nav-tabs" role="tablist">
				<?php foreach ( $categories as $key => $category ) : ?>
					<li class="nav-item" role="presentation">
						<button class="nav-link <?php echo 0 === $key ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#<?php echo esc_attr( $category->slug ); ?>" type="button" role="tab" aria-controls="#<?php echo esc_attr( $category->slug ); ?>" aria-selected="<?php echo 0 === $key ? 'true' : ''; ?>"><?php echo esc_html( $category->name ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="tab-content">
				<?php foreach ( $categories as $key => $category ) :
					$args = array(
						'numberposts' => 100,
						'post_type'   => 'ubc_dpp_logs',
						'order'       => 'DESC',
						'orderby'     => 'date',
						'post_status' => 'publish',
						'tax_query' => array(
							array(
							'taxonomy' => 'ubc_dpp_logs_category',
							'field' => 'slug',
							'terms' => $category->slug,
							),
						),
					);
					$logs = get_posts( $args );
					?>
					<div class="tab-pane fade show <?php echo 0 === $key ? 'active' : ''; ?>" id="<?php echo esc_attr( $category->slug ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $category->slug ); ?>-tab">
						<table class="table">
							<thead>
								<tr>
									<th scope="col">Date</th>
									<th scope="col">Message</th>
									<th scope="col">File</th>
									<th scope="col">Line</th>
									<th scope="col">Variables</th>
									<th scope="col" class="hidden">Variables Content</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $logs as $key => $log ) :
									$variables = get_post_meta( $log->ID, 'variables', true );
									$file      = get_post_meta( $log->ID, 'file', true );
									$line      = get_post_meta( $log->ID, 'line', true );
									?>
									<tr>
										<td><?php echo esc_html( $log->post_date ); ?></td>
										<td><?php echo wp_kses_post( $log->post_content ); ?></td>
										<td><?php echo wp_kses_post( $file ); ?></td>
										<td><?php echo wp_kses_post( $line ); ?></td>
										<td>
											<button type="button" class="btn btn-info view-log-button text-white">View</button>
										</td>
										<td class="hidden variable-contents"><?php echo print_r( $variables, true ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Modal -->
			<div class="modal fade" id="popupModal" tabindex="-1" role="dialog" aria-labelledby="popupModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered" role="document">
					<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="popupModalLabel">Logged Variables</h5>
						<button type="button" onclick="closeModal();">
						<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body"></div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" onclick="closeModal();">Close</button>
					</div>
					</div>
				</div>
			</div>

			<div class="modal-backdrop fade"></div>
	
			<script>
				jQuery(document).ready(function($){
					$('.view-log-button').on('click', function(e) {
						$modal = $('#popupModal');
						$modal_inner = $modal.find('.modal-body');
						$modalBackdrop = $('.modal-backdrop');
						$content = $(this).closest('tr').find('.variable-contents').html();

						$modal_inner.html( '<pre>' + $content + '</pre>' );
						$modal.addClass('show');
						$modalBackdrop.addClass('show');
					});
				});

				function closeModal() {
					$modal_inner.html('');
					$modal.removeClass('show');
					$modalBackdrop.removeClass('show');
				}
			</script>

			<style>
				.modal.show{
					display: block;
				}
				.modal-backdrop{
					display: none;
				}
				.modal-backdrop.show{
					display: block;
				}
			</style>

			<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
			<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
		<?php
		echo ob_get_clean();
	}//end add_payment_log_menu_content();

	/**
	 * Log error message to database.
	 * 
	 * @param string $message error message to log.
	 * @param string $category category that the current error belongs.
	 * @param array  $variables print variables for debugging purposes.
	 * 
	 * @return void
	 */
	public static function log( $category = 'General', $variables = array(), $message = '' ) {
		// Use debug_backtrace to get details about where this is being called from.
		$bt     = debug_backtrace(); // phpcs:ignore
		$caller = array_shift( $bt );

		$file = $caller['file']; // akin to __FILE__
		$line = $caller['line']; // akin to __LINE__

		$term = get_term_by( 'name', $category, 'ubc_dpp_logs_category' );

		if ( false === $term ) {
			$term    = wp_insert_term( $category, 'ubc_dpp_logs_category' );

			$term_id = $term['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		$post_id = wp_insert_post( array(
			'post_content' => wp_kses_post( $message ),
			'post_type'    => 'ubc_dpp_logs',
			'post_status'  => 'publish',
			'meta_input'   => array(
				'variables' => $variables,
				'file'      => $file,
				'line'      => $line
			)
		));

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		wp_set_object_terms(
			$post_id,
			$term_id,
			'ubc_dpp_logs_category'
		);
	}//end log()

}
