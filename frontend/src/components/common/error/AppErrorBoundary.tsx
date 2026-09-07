import Container from '@mui/material/Container'
import { Component, type ReactNode } from 'react'
import { AppErrorFallback } from './AppErrorFallback'

type AppErrorBoundaryProps = { children: ReactNode }
type AppErrorBoundaryState = { error: Error | null }

/**
 * Catches what the route errorElement cannot show, mainly a broken AppShell that it renders inside.
 * A class because getDerivedStateFromError has no hook equivalent.
 */
export class AppErrorBoundary extends Component<AppErrorBoundaryProps, AppErrorBoundaryState> {
  override state: AppErrorBoundaryState = { error: null }

  static getDerivedStateFromError(error: Error): AppErrorBoundaryState {
    return { error }
  }

  override render(): ReactNode {
    if (this.state.error === null) {
      return this.props.children
    }

    return (
      <Container maxWidth="sm" sx={{ paddingBlock: 6 }}>
        <AppErrorFallback />
      </Container>
    )
  }
}
