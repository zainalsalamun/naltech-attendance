import 'package:flutter/material.dart';
import 'home_page.dart';
import 'history_page.dart';
import '../../profile/pages/profile_page.dart';

class MainPage extends StatefulWidget {
  const MainPage({super.key, this.pages});
  final List<Widget>? pages;
  @override
  State<MainPage> createState() => _MainPageState();
}

class _MainPageState extends State<MainPage> {
  int _selectedIndex = 0;
  final Set<int> _visited = {0};
  late final List<Widget> _pages =
      widget.pages ?? const [HomePage(), HistoryPage(), ProfilePage()];

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: _selectedIndex == 0,
        onPopInvokedWithResult: (didPop, result) {
          if (!didPop && _selectedIndex != 0) {
            setState(() => _selectedIndex = 0);
          }
        },
        child: Scaffold(
          body: IndexedStack(
              index: _selectedIndex,
              children: List.generate(
                  3,
                  (index) => _visited.contains(index)
                      ? (index == 0 && widget.pages == null
                          ? HomePage(
                              active: _selectedIndex == 0,
                              onProfile: () => setState(() {
                                    _visited.add(2);
                                    _selectedIndex = 2;
                                  }))
                          : _pages[index])
                      : const SizedBox.shrink())),
          bottomNavigationBar: DecoratedBox(
            decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.surface,
                border: Border(
                    top: BorderSide(
                        color: Theme.of(context).colorScheme.outline))),
            child: SafeArea(
                top: false,
                child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: List.generate(3, (index) {
                      const labels = ['Beranda', 'Riwayat', 'Profil'];
                      const icons = [
                        Icons.home_outlined,
                        Icons.history_outlined,
                        Icons.person_outline
                      ];
                      final selected = _selectedIndex == index;
                      final color = selected
                          ? Theme.of(context).colorScheme.primary
                          : Theme.of(context).colorScheme.onSurfaceVariant;
                      return Expanded(
                          child: Semantics(
                              selected: selected,
                              button: true,
                              child: InkWell(
                                  onTap: () => setState(() {
                                        _visited.add(index);
                                        _selectedIndex = index;
                                      }),
                                  child: Padding(
                                      padding: const EdgeInsets.fromLTRB(
                                          8, 10, 8, 8),
                                      child: Column(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Icon(
                                                selected && index == 0
                                                    ? Icons.home
                                                    : icons[index],
                                                color: color,
                                                size: 25),
                                            const SizedBox(height: 4),
                                            Text(labels[index],
                                                style: TextStyle(
                                                    color: color,
                                                    fontSize: 12,
                                                    fontWeight: selected
                                                        ? FontWeight.w700
                                                        : FontWeight.w400)),
                                            const SizedBox(height: 7),
                                            Container(
                                                height: 3,
                                                width: 24,
                                                decoration: BoxDecoration(
                                                    color: selected
                                                        ? color
                                                        : Colors.transparent,
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                            2))),
                                          ])))));
                    }))),
          ),
        ),
      );
}
