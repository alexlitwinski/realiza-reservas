/**
 * JavaScript para formulário de reservas do frontend
 * CORRIGIDO: Problema de sintaxe que impedia o carregamento
 */

jQuery(document).ready(function($) {
    
    var currentStep = 1;
    var selectionMode = $('#selection_mode').val() || 'table';
    var totalSteps = 4;
    
    // Inicializar formulário
    initForm();
    
    function initForm() {
        // Botões de navegação
        setupNavigation();
        
        // Listener para mudança de data
        $('#reservation_date').on('change', function() {
            loadAvailableTimes();
        });
        
        // Seleção de área (modo área)
        $(document).on('change', 'input[name="area_selection"]', function() {
            if ($(this).is(':checked')) {
                var areaType = $(this).val();
                $('#selected_area').val(areaType);
                $('#continue-to-step3').prop('disabled', false);
            }
        });
        
        // Seleção de mesa/local (outros modos)
        $(document).on('change', 'input[name="table_selection"]', function() {
            if ($(this).is(':checked')) {
                var value = $(this).val();
                $('#selected_table_id').val(value);
                $('#continue-to-step3').prop('disabled', false);
            }
        });
        
        // Submissão do formulário
        $('#zuzunely-frontend-form').on('submit', submitReservation);
        
        // Máscara para telefone
        $('#customer_phone').on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            var formattedValue = '';
            
            if (value.length > 0) {
                if (value.length <= 2) {
                    formattedValue = '(' + value;
                } else if (value.length <= 7) {
                    formattedValue = '(' + value.substr(0, 2) + ') ' + value.substr(2);
                } else {
                    formattedValue = '(' + value.substr(0, 2) + ') ' + value.substr(2, 5) + '-' + value.substr(7, 4);
                }
            }
            
            $(this).val(formattedValue);
        });
    }
    
    // Carregar horários disponíveis
    function loadAvailableTimes() {
        console.log('=== INICIO loadAvailableTimes ===');
        
        var selectedDate = $('#reservation_date').val();
        var $timeSelect = $('#reservation_time');
        var $timeDescription = $('#time-description');
        
        console.log('Data selecionada:', selectedDate);
        console.log('zuzunely_frontend object:', typeof zuzunely_frontend !== 'undefined' ? zuzunely_frontend : 'NÃO DEFINIDO');
        
        if (!selectedDate) {
            console.log('Nenhuma data selecionada, saindo...');
            $timeSelect.prop('disabled', true)
                       .html('<option value="">Primeiro selecione uma data</option>');
            $timeDescription.hide();
            return;
        }
        
        // Validação básica: data não pode ser no passado
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var selectedDateObj = new Date(selectedDate + 'T00:00:00');
        selectedDateObj.setHours(0, 0, 0, 0);
        
        console.log('Hoje:', today);
        console.log('Data selecionada (objeto):', selectedDateObj);
        
        if (selectedDateObj < today) {
            console.log('Data no passado, mostrando erro...');
            $timeSelect.prop('disabled', true)
                       .html('<option value="">Data não pode ser no passado</option>');
            $timeDescription.text('A data selecionada não pode ser no passado.').show();
            showMessage('A data selecionada não pode ser no passado.', 'error');
            return;
        }
        
        // Verificar se zuzunely_frontend está definido
        if (typeof zuzunely_frontend === 'undefined') {
            console.log('ERRO: zuzunely_frontend não está definido');
            $timeSelect.prop('disabled', true)
                       .html('<option value="">Erro de configuração</option>');
            showMessage('Erro de configuração do sistema.', 'error');
            return;
        }
        
        // Mostrar loading
        console.log('Mostrando loading...');
        $timeSelect.prop('disabled', true)
                   .html('<option value="">Carregando horários...</option>');
        $timeDescription.text('').hide();
        
        // Preparar dados para AJAX
        var ajaxData = {
            action: 'zuzunely_get_available_times',
            nonce: zuzunely_frontend.nonce,
            date: selectedDate
        };
        
        console.log('Dados para AJAX:', ajaxData);
        console.log('URL AJAX:', zuzunely_frontend.ajaxurl);
        
        // Fazer requisição AJAX
        $.ajax({
            url: zuzunely_frontend.ajaxurl,
            type: 'POST',
            data: ajaxData,
            beforeSend: function() {
                console.log('Enviando requisição AJAX...');
            },
            success: function(response) {
                console.log('Resposta AJAX recebida:', response);
                
                if (response.success) {
                    console.log('Resposta de sucesso!');
                    var times = response.data.times;
                    var earliest = response.data.earliest;
                    var latest = response.data.latest;
                    var minAdvanceHours = response.data.min_advance_hours || 2;
                    var isToday = response.data.is_today;
                    
                    console.log('Times array:', times);
                    console.log('Earliest:', earliest);
                    console.log('Latest:', latest);
                    console.log('Is today:', isToday);
                    
                    // Limpar e preencher select
                    $timeSelect.empty();
                    $timeSelect.append('<option value="">Selecione o horário</option>');
                    
                    if (times && times.length > 0) {
                        console.log('Adicionando', times.length, 'horários ao select...');
                        $.each(times, function(index, time) {
                            console.log('Adicionando horário:', time.value, '-', time.label);
                            $timeSelect.append('<option value="' + time.value + '">' + time.label + '</option>');
                        });
                        
                        $timeSelect.prop('disabled', false);
                        console.log('Select habilitado com', $timeSelect.find('option').length, 'opções');
                        
                        // Mostrar informação sobre horários disponíveis
                        var infoText = 'Horários disponíveis: ' + earliest + ' às ' + latest;
                        if (isToday) {
                            infoText += ' (Antecedência mínima: ' + minAdvanceHours + ' horas)';
                        }
                        $timeDescription.text(infoText).show();
                        
                        // Debug info
                        if (response.data.debug) {
                            console.log('Debug info do servidor:', response.data.debug);
                        }
                    } else {
                        console.log('Nenhum horário disponível');
                        $timeSelect.append('<option value="">Nenhum horário disponível</option>');
                        $timeDescription.text('Não há horários disponíveis para esta data.').show();
                    }
                } else {
                    console.log('Erro na resposta:', response.data);
                    $timeSelect.empty().append('<option value="">Erro ao carregar horários</option>');
                    $timeDescription.text(response.data || 'Erro ao carregar horários disponíveis.').show();
                    showMessage(response.data || 'Erro ao carregar horários disponíveis.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('Erro AJAX completo:');
                console.log('XHR:', xhr);
                console.log('Status:', status);
                console.log('Error:', error);
                console.log('Response Text:', xhr.responseText);
                
                $timeSelect.empty().append('<option value="">Erro de conexão</option>');
                $timeDescription.text('Erro de conexão. Tente novamente.').show();
                showMessage('Erro de conexão ao carregar horários.', 'error');
            },
            complete: function() {
                console.log('Requisição AJAX finalizada');
                console.log('=== FIM loadAvailableTimes ===');
            }
        });
    }
    
    function setupNavigation() {
        // Passo 1 → Passo 2
        $('#continue-to-step2').on('click', function() {
            var date = $('#reservation_date').val();
            var time = $('#reservation_time').val();
            var guestsCount = $('#guests_count').val();
            
            console.log('Validando passo 1:', {date: date, time: time, guests: guestsCount});
            
            if (!date) {
                showMessage('Por favor, selecione uma data.', 'error');
                return;
            }
            
            if (!time) {
                showMessage('Por favor, selecione um horário.', 'error');
                return;
            }
            
            if (!guestsCount) {
                showMessage('Por favor, selecione o número de pessoas.', 'error');
                return;
            }
            
            // Validação simplificada de antecedência - apenas para hoje
            var selectedDate = new Date(date + 'T00:00:00');
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            selectedDate.setHours(0, 0, 0, 0);
            
            // Se for hoje, validar horário com antecedência
            if (selectedDate.getTime() === today.getTime()) {
                var minAdvanceHours = parseInt(zuzunely_frontend.min_advance_hours) || 2;
                var selectedDateTime = new Date(date + 'T' + time + ':00');
                var now = new Date();
                var minDateTime = new Date(now.getTime() + (minAdvanceHours * 60 * 60 * 1000));
                
                if (selectedDateTime < minDateTime) {
                    showMessage('É necessário um mínimo de ' + minAdvanceHours + ' horas de antecedência para reservas hoje.', 'error');
                    return;
                }
            }
            
            console.log('Passo 1 validado, indo para passo 2');
            goToStep(2);
        });
        
        // Buscar mesas (modos table/saloon)
        $('#search-tables-btn').on('click', searchAvailableTables);
        
        // Passo 2 → Passo 3
        $('#continue-to-step3').on('click', function() {
            if (selectionMode === 'area') {
                var selectedArea = $('input[name="area_selection"]:checked');
                if (selectedArea.length === 0) {
                    showMessage('Por favor, selecione uma área.', 'error');
                    return;
                }
            } else {
                var selectedTable = $('input[name="table_selection"]:checked');
                if (selectedTable.length === 0) {
                    showMessage('Por favor, selecione uma mesa.', 'error');
                    return;
                }
            }
            
            goToStep(3);
        });
        
        // Passo 3 → Passo 4
        $('#continue-to-step4').on('click', function() {
            var name = $('#customer_name').val().trim();
            var phone = $('#customer_phone').val().trim();
            var email = $('#customer_email').val().trim();
            
            if (!name || !phone || !email) {
                showMessage('Por favor, preencha todos os campos obrigatórios.', 'error');
                return;
            }
            
            updateReservationSummary();
            goToStep(4);
        });
        
        // Botões de voltar
        $('#back-to-step1').on('click', function() { goToStep(1); });
        $('#back-to-step2').on('click', function() { goToStep(2); });
        $('#back-to-step3').on('click', function() { goToStep(3); });
        
        // Clique nos indicadores de passo
        $('.zuzunely-step-indicator .step').on('click', function() {
            var targetStep = parseInt($(this).data('step'));
            if (targetStep <= currentStep || $(this).hasClass('completed')) {
                goToStep(targetStep);
            }
        });
    }
    
    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;
        
        // Esconder todos os passos
        $('.zuzunely-step').removeClass('active');
        $('.zuzunely-step-indicator .step').removeClass('active');
        
        // Mostrar passo atual
        $('.zuzunely-step[data-step="' + step + '"]').addClass('active');
        $('.zuzunely-step-indicator .step[data-step="' + step + '"]').addClass('active');
        
        // Marcar passos anteriores como completos
        for (var i = 1; i < step; i++) {
            $('.zuzunely-step-indicator .step[data-step="' + i + '"]').addClass('completed');
        }
        
        currentStep = step;
        
        // Scroll para o topo do formulário
        $('html, body').animate({
            scrollTop: $('#zuzunely-reservation-form').offset().top - 50
        }, 300);
    }
    
    function searchAvailableTables() {
        var date = $('#reservation_date').val();
        var time = $('#reservation_time').val();
        var guestsCount = $('#guests_count').val();
        
        // Validar campos
        if (!date || !time || !guestsCount) {
            showMessage('Por favor, preencha todos os campos obrigatórios.', 'error');
            return;
        }
        
        // Validação simplificada de antecedência - apenas para hoje
        var selectedDate = new Date(date + 'T00:00:00');
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        selectedDate.setHours(0, 0, 0, 0);
        
        // Se for hoje, validar horário com antecedência
        if (selectedDate.getTime() === today.getTime()) {
            var minAdvanceHours = parseInt(zuzunely_frontend.min_advance_hours) || 2;
            var selectedDateTime = new Date(date + 'T' + time + ':00');
            var now = new Date();
            var minDateTime = new Date(now.getTime() + (minAdvanceHours * 60 * 60 * 1000));
            
            if (selectedDateTime < minDateTime) {
                showMessage('É necessário um mínimo de ' + minAdvanceHours + ' horas de antecedência.', 'error');
                return;
            }
        }
        
        // Desabilitar botão e mostrar loading
        var $btn = $('#search-tables-btn');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Buscando mesas disponíveis...');
        
        // Fazer requisição AJAX
        $.ajax({
            url: zuzunely_frontend.ajaxurl,
            type: 'POST',
            data: {
                action: 'zuzunely_search_available_tables',
                nonce: zuzunely_frontend.nonce,
                date: date,
                time: time,
                guests_count: guestsCount,
                selection_mode: selectionMode
            },
            success: function(response) {
                if (response.success) {
                    $('#available-tables-container').html(response.data.html);
                    
                    var message = '';
                    if (selectionMode === 'table') {
                        message = 'Encontradas ' + response.data.count + ' mesa(s) disponível(is).';
                    } else {
                        message = 'Encontrados locais disponíveis.';
                    }
                    
                    showMessage(message, 'success');
                } else {
                    showMessage(response.data || 'Nenhuma mesa disponível encontrada.', 'error');
                }
            },
            error: function() {
                showMessage('Erro ao processar reserva. Tente novamente.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function updateReservationSummary() {
        var date = $('#reservation_date').val();
        var time = $('#reservation_time').val();
        var guestsCount = $('#guests_count').val();
        var customerName = $('#customer_name').val();
        var customerPhone = $('#customer_phone').val();
        var customerEmail = $('#customer_email').val();
        var notes = $('#notes').val();
        
        // CORREÇÃO: Formatear data corretamente para evitar problema de timezone
        var dateObj = new Date(date + 'T12:00:00'); // Usar meio-dia para evitar problemas de timezone
        var formattedDate = dateObj.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: 'America/Sao_Paulo' // Forçar timezone brasileiro
        });
        
        var summaryHtml = '<div class="zuzunely-reservation-summary">';
        summaryHtml += '<h5>Resumo da Reserva:</h5>';
        summaryHtml += '<p><strong>Data:</strong> ' + formattedDate + '</p>';
        summaryHtml += '<p><strong>Horário:</strong> ' + time + '</p>';
        summaryHtml += '<p><strong>Número de pessoas:</strong> ' + guestsCount + '</p>';
        
        // Informações sobre local baseado no modo de seleção
        if (selectionMode === 'table') {
            var selectedTable = $('input[name="table_selection"]:checked');
            if (selectedTable.length > 0) {
                var tableName = selectedTable.data('table-name');
                var saloonName = selectedTable.data('saloon-name');
                var capacity = selectedTable.data('capacity');
                summaryHtml += '<p><strong>Mesa:</strong> ' + tableName + ' (Salão: ' + saloonName + ', Capacidade: ' + capacity + ' pessoas)</p>';
            }
        } else if (selectionMode === 'saloon') {
            var selectedSaloon = $('input[name="table_selection"]:checked');
            if (selectedSaloon.length > 0) {
                var saloonName = selectedSaloon.data('saloon-name');
                summaryHtml += '<p><strong>Local:</strong> ' + saloonName + ' (mesa será escolhida automaticamente)</p>';
            }
        } else if (selectionMode === 'area') {
            var selectedArea = $('input[name="area_selection"]:checked');
            if (selectedArea.length > 0) {
                var areaType = selectedArea.val();
                var areaName = areaType === 'internal' ? 'Área Interna' : 'Área Externa';
                summaryHtml += '<p><strong>Área:</strong> ' + areaName + ' (mesa será escolhida automaticamente)</p>';
            } else {
                summaryHtml += '<p><strong>Local:</strong> Mesa será escolhida automaticamente</p>';
            }
        }
        
        summaryHtml += '<p><strong>Nome:</strong> ' + customerName + '</p>';
        summaryHtml += '<p><strong>Telefone:</strong> ' + customerPhone + '</p>';
        summaryHtml += '<p><strong>E-mail:</strong> ' + customerEmail + '</p>';
        if (notes) {
            summaryHtml += '<p><strong>Observações:</strong> ' + notes + '</p>';
        }
        summaryHtml += '</div>';
        
        $('#reservation-summary').html(summaryHtml);
    }
    
    function submitReservation(e) {
        e.preventDefault();
        
        // Validar campos obrigatórios
        if (!validateForm()) {
            return;
        }
        
        // Desabilitar botão de submit
        var $btn = $('#confirm-reservation');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Processando reserva...');
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: zuzunely_frontend.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    
                    // Limpar formulário e voltar ao início após 3 segundos
                    setTimeout(function() {
                        resetForm();
                        goToStep(1);
                    }, 3000);
                } else {
                    showMessage(response.data || 'Erro ao processar reserva. Tente novamente.', 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                showMessage('Erro ao processar reserva. Tente novamente.', 'error');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function validateForm() {
        var errors = [];
        var guestsCount = parseInt($('#guests_count').val());
        var maxGuests = parseInt(zuzunely_frontend.max_guests);
        var date = $('#reservation_date').val();
        var time = $('#reservation_time').val();
        
        // Validar passo 1
        if (!date) {
            errors.push('Data da reserva é obrigatória');
        }
        if (!time) {
            errors.push('Horário da reserva é obrigatório');
        }
        if (!guestsCount) {
            errors.push('Número de pessoas é obrigatório');
        } else if (guestsCount > maxGuests) {
            errors.push('O número máximo de pessoas por reserva é ' + maxGuests);
        }
        
        // Validar antecedência mínima na validação final
        if (date && time) {
            var selectedDate = new Date(date + 'T00:00:00');
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            selectedDate.setHours(0, 0, 0, 0);
            
            // Se for hoje, validar horário com antecedência
            if (selectedDate.getTime() === today.getTime()) {
                var minAdvanceHours = parseInt(zuzunely_frontend.min_advance_hours) || 2;
                var selectedDateTime = new Date(date + 'T' + time + ':00');
                var now = new Date();
                var minDateTime = new Date(now.getTime() + (minAdvanceHours * 60 * 60 * 1000));
                
                if (selectedDateTime < minDateTime) {
                    errors.push('É necessário um mínimo de ' + minAdvanceHours + ' horas de antecedência.');
                }
            }
        }
        
        // Validar passo 2
        if (selectionMode === 'area') {
            if (!$('#selected_area').val()) {
                errors.push('Selecione uma área');
            }
        } else {
            if (!$('#selected_table_id').val()) {
                if (selectionMode === 'table') {
                    errors.push('Selecione uma mesa');
                } else {
                    errors.push('Selecione um local');
                }
            }
        }
        
        // Validar dados do cliente
        if (!$('#customer_name').val()) {
            errors.push('Nome é obrigatório');
        }
        if (!$('#customer_phone').val()) {
            errors.push('Telefone é obrigatório');
        }
        if (!$('#customer_email').val()) {
            errors.push('E-mail é obrigatório');
        }
        
        // Validar termos
        if (!$('#accept_terms').is(':checked')) {
            errors.push('Você deve aceitar os termos e condições');
        }
        
        if (errors.length > 0) {
            showMessage(errors.join('<br>'), 'error');
            return false;
        }
        
        return true;
    }
    
    function resetForm() {
        $('#zuzunely-frontend-form')[0].reset();
        $('#selected_table_id').val('');
        $('#selected_area').val('');
        $('#available-tables-container').html('<p>Use o botão acima para ver as opções disponíveis.</p>');
        $('#continue-to-step3').prop('disabled', true);
        $('#reservation-summary').empty();
        
        // Resetar select de horário
        $('#reservation_time').prop('disabled', true)
                             .html('<option value="">Primeiro selecione uma data</option>');
        $('#time-description').hide();
        
        currentStep = 1;
    }
    
    function showMessage(message, type) {
        var messageClass = 'zuzunely-message ' + type;
        var messageHtml = '<div class="' + messageClass + '">' + message + '</div>';
        
        var $container = $('#zuzunely-messages');
        $container.html(messageHtml);
        
        // Scroll para a mensagem
        $('html, body').animate({
            scrollTop: $container.offset().top - 50
        }, 300);
        
        // Remover mensagem após 5 segundos (exceto mensagens de sucesso)
        if (type !== 'success') {
            setTimeout(function() {
                $container.empty();
            }, 5000);
        }
    }
});