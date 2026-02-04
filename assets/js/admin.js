/**
 * FluentCRM Edition Contacts - Admin JavaScript
 */
(function($) {
    'use strict';
    
    var FCEF = {
        currentPage: 1,
        perPage: 20,
        currentCourse: '',
        currentEdition: '',
        contacts: [],
        
        init: function() {
            this.bindEvents();
            this.loadStats();
        },
        
        bindEvents: function() {
            $('#fcef-course').on('change', this.onCourseChange.bind(this));
            $('#fcef-edition').on('change', this.onEditionChange.bind(this));
            $('#fcef-filter-btn').on('click', this.filterContacts.bind(this));
            $('#fcef-reset-btn').on('click', this.reset.bind(this));
            $('#fcef-select-all').on('change', this.toggleSelectAll.bind(this));
            $(document).on('click', '.fcef-edition-item[data-course][data-edition]', this.onStatClick.bind(this));
            $('#fcef-export-btn').on('click', this.exportContacts.bind(this));

            // Copy email button
            $(document).on('click', '.fcef-copy-email', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var email = $(this).data('email');
                FCEF.copyToClipboard(email, $(this));
            });

            // Make table rows clickable (but not checkbox and copy button)
            $(document).on('click', '.fcef-table tbody tr', function(e) {
                // Don't trigger if clicking checkbox or copy button
                if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('.fcef-copy-email').length) return;

                var link = $(this).find('.fcef-contact-link').attr('href');
                if (link) {
                    window.open(link, '_blank');
                }
            });
        },

        copyToClipboard: function(text, $btn) {
            navigator.clipboard.writeText(text).then(function() {
                var originalHtml = $btn.html();
                $btn.html('<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>');
                $btn.addClass('fcef-copied');
                setTimeout(function() {
                    $btn.html(originalHtml);
                    $btn.removeClass('fcef-copied');
                }, 1500);
            });
        },

        exportContacts: function() {
            if (!this.currentCourse || !this.currentEdition) return;

            this.showLoading();

            $.ajax({
                url: fcefData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fcef_export_contacts',
                    nonce: fcefData.nonce,
                    course: this.currentCourse,
                    edition: this.currentEdition
                },
                success: function(response) {
                    FCEF.hideLoading();

                    if (response.success) {
                        FCEF.downloadCSV(response.data.csv_data, response.data.filename);
                    }
                },
                error: function() {
                    FCEF.hideLoading();
                    alert('Export failed. Please try again.');
                }
            });
        },

        downloadCSV: function(data, filename) {
            var csv = data.map(function(row) {
                return row.map(function(cell) {
                    // Escape quotes and wrap in quotes if contains comma or quote
                    var escaped = String(cell || '').replace(/"/g, '""');
                    if (escaped.indexOf(',') !== -1 || escaped.indexOf('"') !== -1 || escaped.indexOf('\n') !== -1) {
                        escaped = '"' + escaped + '"';
                    }
                    return escaped;
                }).join(',');
            }).join('\n');

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        
        showLoading: function() {
            $('#fcef-loading').fadeIn(150);
        },
        
        hideLoading: function() {
            $('#fcef-loading').fadeOut(150);
        },
        
        onCourseChange: function(e) {
            var course = $(e.target).val();
            var $editionSelect = $('#fcef-edition');
            var $filterBtn = $('#fcef-filter-btn');
            
            this.currentCourse = course;
            this.currentEdition = '';
            
            if (!course) {
                $editionSelect.html('<option value="">Select Edition...</option>').prop('disabled', true);
                $filterBtn.prop('disabled', true);
                return;
            }
            
            $editionSelect.html('<option value="">Loading...</option>').prop('disabled', true);
            
            $.ajax({
                url: fcefData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fcef_get_editions',
                    nonce: fcefData.nonce,
                    course: course
                },
                success: function(response) {
                    if (response.success) {
                        var editions = response.data.editions;
                        var html = '<option value="">Select Edition...</option>';
                        
                        if (editions.length > 0) {
                            editions.forEach(function(edition) {
                                html += '<option value="' + FCEF.escapeHtml(edition) + '">' + FCEF.escapeHtml(edition) + '</option>';
                            });
                            $editionSelect.html(html).prop('disabled', false);
                        } else {
                            $editionSelect.html('<option value="">No editions found</option>').prop('disabled', true);
                        }
                    }
                }
            });
        },
        
        onEditionChange: function(e) {
            this.currentEdition = $(e.target).val();
            $('#fcef-filter-btn').prop('disabled', !this.currentEdition);
        },
        
        filterContacts: function() {
            if (!this.currentCourse || !this.currentEdition) return;
            this.currentPage = 1;
            this.loadContacts();
            this.loadEditionStats();
        },
        
        loadContacts: function() {
            this.showLoading();
            
            $.ajax({
                url: fcefData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fcef_filter_contacts',
                    nonce: fcefData.nonce,
                    course: this.currentCourse,
                    edition: this.currentEdition,
                    page: this.currentPage,
                    per_page: this.perPage
                },
                success: function(response) {
                    FCEF.hideLoading();
                    
                    if (response.success) {
                        FCEF.contacts = response.data.contacts;
                        FCEF.renderContacts(response.data);
                        $('#fcef-results').slideDown(200);
                        
                        setTimeout(function() {
                            $('html, body').animate({
                                scrollTop: $('#fcef-results').offset().top - 80
                            }, 300);
                        }, 100);
                    }
                },
                error: function() {
                    FCEF.hideLoading();
                }
            });
        },
        
        loadEditionStats: function() {
            $.ajax({
                url: fcefData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fcef_get_stats',
                    nonce: fcefData.nonce,
                    course: this.currentCourse,
                    edition: this.currentEdition
                },
                success: function(response) {
                    if (response.success) {
                        FCEF.renderEditionStats(response.data);
                    }
                }
            });
        },
        
        renderEditionStats: function(stats) {
            $('#stat-total-contacts').text(FCEF.formatNumber(stats.total_contacts));
            $('#stat-subscribed').text(FCEF.formatNumber(stats.subscribed));
            $('#stat-revenue').text(fcefData.currency + FCEF.formatNumber(stats.total_revenue, 2));
            $('#stat-orders').text(FCEF.formatNumber(stats.order_count));
            $('#stat-avg').text(fcefData.currency + FCEF.formatNumber(stats.avg_order_value, 2));
        },
        
        renderContacts: function(data) {
            var courseName = fcefData.courses[this.currentCourse]?.label || '';
            
            $('#fcef-results-title').text(this.currentEdition);
            $('#fcef-results-count').text(data.total + ' contact' + (data.total !== 1 ? 's' : ''));
            
            var $tbody = $('#fcef-contacts-body');
            $tbody.empty();
            
            if (data.contacts.length === 0) {
                $tbody.html(
                    '<tr><td colspan="10" class="fcef-empty-state">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>' +
                    '<p>No contacts found for this edition</p>' +
                    '</td></tr>'
                );
                $('#fcef-pagination').hide();
                return;
            }
            
            data.contacts.forEach(function(contact) {
                var contactUrl = fcefData.fluentcrmUrl + contact.id;
                var initials = FCEF.getInitials(contact.first_name, contact.last_name, contact.email);
                var fullName = ((contact.first_name || '') + ' ' + (contact.last_name || '')).trim() || 'No Name';
                var productName = contact.product_name || '-';
                var orderCountText = contact.order_count_text || '';
                var orderTotal = contact.order_total ? fcefData.currency + FCEF.formatNumber(contact.order_total, 2) : '-';
                var phone = contact.phone || '-';
                var specialities = contact.specialities || '-';
                var examDate = contact.exam_date || '-';

                var row = '<tr data-id="' + contact.id + '">';
                row += '<td><input type="checkbox" class="fcef-contact-cb" value="' + contact.id + '"></td>';
                row += '<td>';
                row += '<a href="' + contactUrl + '" target="_blank" class="fcef-contact-link">';
                row += '<div class="fcef-avatar">' + initials + '</div>';
                row += '<div class="fcef-contact-info">';
                row += '<div class="fcef-contact-name">' + FCEF.escapeHtml(fullName) + '</div>';
                row += '<div class="fcef-contact-email-row">';
                row += '<span class="fcef-contact-email">' + FCEF.escapeHtml(contact.email) + '</span>';
                row += '</div>';
                // ASiT Member badge
                if (contact.is_asit_member) {
                    row += '<span class="fcef-asit-badge">ASiT Member</span>';
                }
                row += '</div>';
                row += '</a>';
                // Copy email button (outside the link)
                row += '<button type="button" class="fcef-copy-email" data-email="' + FCEF.escapeHtml(contact.email) + '" title="Copy email">';
                row += '<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>';
                row += '</button>';
                row += '</td>';

                // Phone column
                row += '<td class="fcef-col-phone-cell">' + FCEF.escapeHtml(phone) + '</td>';

                // ========== COURSE/PRODUCT NAME COLUMN ==========
                // To hide this column, comment out the next line and also comment out
                // the <th class="fcef-col-course"> in fluentcrm-edition-contacts.php
                row += '<td><div class="fcef-product-wrapper">';
                row += '<span class="fcef-product-name">' + FCEF.escapeHtml(productName) + '</span>';
                if (orderCountText) {
                    row += '<span class="fcef-order-count-badge">' + FCEF.escapeHtml(orderCountText) + '</span>';
                }
                row += '</div></td>';
                // ================================================

                // Price column
                row += '<td><span class="fcef-price">' + orderTotal + '</span></td>';
                row += '<td><span class="fcef-badge fcef-badge-primary">' + FCEF.escapeHtml(contact.edition) + '</span></td>';

                // Specialities column
                row += '<td class="fcef-col-specialities-cell">' + FCEF.escapeHtml(specialities) + '</td>';

                // Exam Date column
                row += '<td class="fcef-col-exam-date-cell">' + FCEF.escapeHtml(examDate) + '</td>';

                row += '<td><span class="fcef-status fcef-status-' + contact.status + '">' + contact.status + '</span></td>';
                row += '<td style="color: var(--fc-text-secondary); font-size: 13px;">' + FCEF.formatDate(contact.created_at) + '</td>';
                row += '</tr>';

                $tbody.append(row);
            });
            
            this.renderPagination(data);
        },
        
        renderPagination: function(data) {
            var $pagination = $('#fcef-pagination');
            $pagination.empty();
            
            if (data.pages <= 1) {
                $pagination.hide();
                return;
            }
            
            $pagination.show();
            
            // Previous
            $pagination.append('<button class="fcef-page-btn fcef-prev" ' + (this.currentPage <= 1 ? 'disabled' : '') + '>← Prev</button>');
            
            // Page numbers
            var startPage = Math.max(1, this.currentPage - 2);
            var endPage = Math.min(data.pages, this.currentPage + 2);
            
            if (startPage > 1) {
                $pagination.append('<button class="fcef-page-btn" data-page="1">1</button>');
                if (startPage > 2) $pagination.append('<span class="fcef-page-info">...</span>');
            }
            
            for (var i = startPage; i <= endPage; i++) {
                $pagination.append('<button class="fcef-page-btn' + (i === this.currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>');
            }
            
            if (endPage < data.pages) {
                if (endPage < data.pages - 1) $pagination.append('<span class="fcef-page-info">...</span>');
                $pagination.append('<button class="fcef-page-btn" data-page="' + data.pages + '">' + data.pages + '</button>');
            }
            
            // Next
            $pagination.append('<button class="fcef-page-btn fcef-next" ' + (this.currentPage >= data.pages ? 'disabled' : '') + '>Next →</button>');
            
            // Info
            var start = (this.currentPage - 1) * this.perPage + 1;
            var end = Math.min(this.currentPage * this.perPage, data.total);
            $pagination.append('<span class="fcef-page-info" style="margin-left: 16px;">' + start + '-' + end + ' of ' + data.total + '</span>');
            
            // Events
            var self = this;
            $pagination.find('.fcef-prev').on('click', function() {
                if (self.currentPage > 1) {
                    self.currentPage--;
                    self.loadContacts();
                }
            });
            
            $pagination.find('.fcef-next').on('click', function() {
                if (self.currentPage < data.pages) {
                    self.currentPage++;
                    self.loadContacts();
                }
            });
            
            $pagination.find('.fcef-page-btn[data-page]').on('click', function() {
                var page = parseInt($(this).data('page'));
                if (page !== self.currentPage) {
                    self.currentPage = page;
                    self.loadContacts();
                }
            });
        },
        
        loadStats: function() {
            $.ajax({
                url: fcefData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fcef_get_all_editions',
                    nonce: fcefData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FCEF.renderStats(response.data.courses);
                    }
                }
            });
        },
        
        renderStats: function(courses) {
            var $container = $('#fcef-stats-container');
            $container.empty();
            
            var courseIcons = {
                'frcs_edition': '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/></svg>',
                'default': '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>'
            };
            
            for (var courseField in courses) {
                var course = courses[courseField];
                var icon = courseIcons[courseField] || courseIcons['default'];
                
                var card = '<div class="fcef-stat-card">';
                card += '<div class="fcef-stat-card-header">';
                card += '<div class="fcef-stat-card-icon">' + icon + '</div>';
                card += '<span class="fcef-stat-card-title">' + FCEF.escapeHtml(course.label) + '</span>';
                card += '</div>';
                
                if (course.editions.length > 0) {
                    card += '<ul class="fcef-edition-list">';
                    course.editions.forEach(function(edition) {
                        card += '<li class="fcef-edition-item" data-course="' + courseField + '" data-edition="' + FCEF.escapeHtml(edition.value) + '">';
                        card += '<span class="fcef-edition-name">' + FCEF.escapeHtml(edition.value) + '</span>';
                        card += '<span class="fcef-edition-count">' + edition.count + '</span>';
                        card += '</li>';
                    });
                    card += '</ul>';
                } else {
                    card += '<div class="fcef-no-editions">No editions yet</div>';
                }
                
                card += '</div>';
                $container.append(card);
            }
        },
        
        onStatClick: function(e) {
            var $item = $(e.currentTarget);
            var course = $item.data('course');
            var edition = String($item.data('edition'));
            
            $('#fcef-course').val(course).trigger('change');
            
            var self = this;
            setTimeout(function() {
                $('#fcef-edition').val(edition);
                self.currentCourse = course;
                self.currentEdition = edition;
                $('#fcef-filter-btn').prop('disabled', false);
                self.filterContacts();
            }, 400);
        },
        
        reset: function() {
            $('#fcef-course').val('');
            $('#fcef-edition').html('<option value="">Select Edition...</option>').prop('disabled', true);
            $('#fcef-filter-btn').prop('disabled', true);
            $('#fcef-results').slideUp(200);
            this.currentCourse = '';
            this.currentEdition = '';
            this.currentPage = 1;
            this.contacts = [];
        },
        
        toggleSelectAll: function(e) {
            $('.fcef-contact-cb').prop('checked', $(e.target).prop('checked'));
        },
        
        getInitials: function(firstName, lastName, email) {
            if (firstName && lastName) {
                return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
            } else if (firstName) {
                return firstName.substring(0, 2).toUpperCase();
            } else if (email) {
                return email.substring(0, 2).toUpperCase();
            }
            return '??';
        },
        
        formatDate: function(dateStr) {
            if (!dateStr) return '-';
            var date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        formatNumber: function(num, decimals) {
            if (num === null || num === undefined) return '0';
            decimals = decimals || 0;
            return parseFloat(num).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },
        
        escapeHtml: function(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    };
    
    $(document).ready(function() {
        FCEF.init();
    });
    
})(jQuery);
